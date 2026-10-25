import { API_BASE_URL } from "./config.js";

export const DEFAULT_TIMEOUT_MS = 10000;

const UNSAFE_METHODS = new Set(["POST", "PUT", "PATCH", "DELETE"]);

let csrfTokenProvider = () => null;
let unauthorizedHandler = null;

/** Jeton envoye en `X-CSRF-Token` sur POST/PUT/PATCH/DELETE ; `fn` sync ou async, null/"" = en-tete omis. */
export function setCsrfTokenProvider(fn) {
  csrfTokenProvider = typeof fn === "function" ? fn : () => null;
}

/** Hook global appele avec l'ApiError sur toute reponse 401, juste avant de la lever. */
export function setUnauthorizedHandler(fn) {
  unauthorizedHandler = typeof fn === "function" ? fn : null;
}

/**
 * .type : "network" (injoignable, CORS, DNS) | "timeout" | "abort" (signal de l'appelant)
 *       | "parse" (2xx au JSON invalide ou null) | "http" (non 2xx, code/details de l'enveloppe { error }).
 * .requestId : en-tete X-Request-Id (a communiquer au support).
 */
export class ApiError extends Error {
  /**
   * @param {string} message
   * @param {{ type?: string, status?: number, code?: string|null, details?: object|null,
   *           requestId?: string|null, cause?: unknown }} [options]
   */
  constructor(message, {
    type = "http",
    status = 0,
    code = null,
    details = null,
    requestId = null,
    cause,
  } = {}) {
    super(message, cause === undefined ? undefined : { cause });
    this.name = "ApiError";
    this.type = type;
    this.status = status;
    this.code = code;
    this.details = details;
    this.requestId = requestId;
  }

  /** Erreur de validation 422 : `details` = { champ: message }. */
  get isValidation() {
    return this.status === 422 && this.code === "VALIDATION_ERROR";
  }
}

function buildSignal(callerSignal, timeoutMs) {
  const signals = [];
  if (callerSignal) signals.push(callerSignal);
  if (timeoutMs > 0) {
    if (typeof AbortSignal.timeout === "function") {
      signals.push(AbortSignal.timeout(timeoutMs));
    } else {
      const ctrl = new AbortController();
      setTimeout(() => ctrl.abort(new DOMException("Timeout", "TimeoutError")), timeoutMs);
      signals.push(ctrl.signal);
    }
  }
  if (signals.length <= 1) return signals[0];
  if (typeof AbortSignal.any === "function") return AbortSignal.any(signals);

  const ctrl = new AbortController();
  for (const s of signals) {
    if (s.aborted) {
      ctrl.abort(s.reason);
      break;
    }
    s.addEventListener("abort", () => ctrl.abort(s.reason), { once: true });
  }
  return ctrl.signal;
}

function transportError(cause, signal, requestId = null) {
  const reason = signal?.aborted ? signal.reason : cause;
  if (reason?.name === "TimeoutError") {
    return new ApiError("Delai d'attente depasse", { type: "timeout", requestId, cause });
  }
  if (reason?.name === "AbortError" || signal?.aborted) {
    return new ApiError("Requete annulee", { type: "abort", requestId, cause });
  }
  return new ApiError("Serveur injoignable", { type: "network", requestId, cause });
}

/**
 * @param {string} path
 * @param {{ method?: string, body?: unknown, headers?: Record<string, string>, signal?: AbortSignal,
 *           timeout?: number }} [options]
 */
async function apiFetch(path, {
  method = "GET",
  body,
  headers,
  signal,
  timeout = DEFAULT_TIMEOUT_MS,
} = {}) {
  const hasBody = body !== undefined && body !== null;
  const finalHeaders = {
    Accept: "application/json",
    ...(hasBody ? { "Content-Type": "application/json" } : {}),
  };

  if (UNSAFE_METHODS.has(method)) {
    const token = await csrfTokenProvider();
    if (token) finalHeaders["X-CSRF-Token"] = String(token);
  }
  Object.assign(finalHeaders, headers || {});

  const finalSignal = buildSignal(signal, timeout);
  /** @type {RequestInit} */
  const options = {
    method,
    headers: finalHeaders,
    // Front (:5500) et API (:8000) sont deux origines : on envoie les cookies (future session).
    credentials: "include",
    ...(hasBody ? { body: JSON.stringify(body) } : {}),
    ...(finalSignal ? { signal: finalSignal } : {}),
  };

  let res;
  try {
    res = await fetch(API_BASE_URL + path, options);
  } catch (cause) {
    // fetch() ne rejette pas sur un status HTTP d'erreur : seulement reseau / CORS / abort / timeout.
    throw transportError(cause, finalSignal);
  }

  const requestId = res.headers.get("x-request-id");

  if (res.status === 204) {
    return null;
  }

  let text;
  try {
    text = await res.text();
  } catch (cause) {
    throw transportError(cause, finalSignal, requestId);
  }

  const isJson = res.headers.get("content-type")?.includes("application/json") ?? false;
  /** @type {any} JSON quelconque : sa forme est verifiee plus bas */
  let payload = text;
  let parseError = null;
  if (isJson) {
    try {
      payload = JSON.parse(text);
    } catch (cause) {
      payload = null;
      parseError = cause;
    }
  }

  if (!res.ok) {
    const err = payload && typeof payload === "object" && payload.error ? payload.error : null;
    const error = new ApiError(err?.message || `HTTP ${res.status}`, {
      type: "http",
      status: res.status,
      code: err?.code ?? null,
      details: err?.details ?? null,
      requestId,
      ...(parseError ? { cause: parseError } : {}),
    });
    if (res.status === 401 && unauthorizedHandler) {
      try {
        unauthorizedHandler(error);
      } catch (hookError) {
        console.error(hookError);
      }
    }
    throw error;
  }

  if (!isJson) {
    return payload;
  }

  if (parseError || payload === null || typeof payload !== "object") {
    throw new ApiError("Reponse JSON invalide", {
      type: "parse",
      status: res.status,
      requestId,
      ...(parseError ? { cause: parseError } : {}),
    });
  }

  return Object.prototype.hasOwnProperty.call(payload, "data") ? payload.data : payload;
}

/** `options` : { signal, timeout (ms, 0 = aucun), headers }. */
export const api = {
  get:   (path, options)       => apiFetch(path, { ...options, method: "GET" }),
  post:  (path, body, options) => apiFetch(path, { ...options, method: "POST",   body }),
  put:   (path, body, options) => apiFetch(path, { ...options, method: "PUT",    body }),
  patch: (path, body, options) => apiFetch(path, { ...options, method: "PATCH",  body }),
  del:   (path, options)       => apiFetch(path, { ...options, method: "DELETE" }),
};
