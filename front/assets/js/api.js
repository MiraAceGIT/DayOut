const META_API_BASE = document.querySelector('meta[name="api-base"]')?.content;
export const API_BASE_URL = META_API_BASE?.trim() || "http://localhost:8000";

/** .type : "network" (serveur injoignable) | "http" (status + code/details de l'enveloppe {error}). */
export class ApiError extends Error {
  constructor(message, { type = "http", status = 0, code = null, details = null } = {}) {
    super(message);
    this.name = "ApiError";
    this.type = type;
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

async function apiFetch(path, { method = "GET", body, headers } = {}) {
  const hasBody = body !== undefined && body !== null;
  const options = {
    method,
    headers: {
      ...(hasBody ? { "Content-Type": "application/json" } : {}),
      ...(headers || {}),
    },
    ...(hasBody ? { body: JSON.stringify(body) } : {}),
  };

  let res;
  try {
    res = await fetch(API_BASE_URL + path, options);
  } catch (cause) {
    // fetch() ne rejette qu'en cas de panne reseau / CORS / abort.
    throw new ApiError("Serveur injoignable", { type: "network", cause });
  }

  if (res.status === 204) {
    return null;
  }

  const isJson = res.headers.get("content-type")?.includes("application/json");
  const payload = isJson ? await res.json() : await res.text();

  if (!res.ok) {
    const err = isJson && payload?.error ? payload.error : null;
    throw new ApiError(
      err?.message || `HTTP ${res.status}`,
      {
        type: "http",
        status: res.status,
        code: err?.code ?? null,
        details: err?.details ?? null,
      }
    );
  }

  // Succes : l'API renvoie { data: ... }, on retourne data directement.
  return isJson && Object.prototype.hasOwnProperty.call(payload, "data")
    ? payload.data
    : payload;
}

export const api = {
  get:   (path)       => apiFetch(path),
  post:  (path, body) => apiFetch(path, { method: "POST",  body }),
  put:   (path, body) => apiFetch(path, { method: "PUT",   body }),
  patch: (path, body) => apiFetch(path, { method: "PATCH", body }),
  del:   (path)       => apiFetch(path, { method: "DELETE" }),
};
