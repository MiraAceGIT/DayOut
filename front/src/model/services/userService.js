import { api } from "../../core/api.js";
import { toEmailVerification, toRegisteredUser } from "../mappers/user/userMapper.js";
import { toEmailVerificationCreateDto, toFormFields, toUserCreateDto } from "../mappers/user/userRequestMapper.js";

/** @typedef {import("../domain/registration.js").RegistrationValues} RegistrationValues */
/** @typedef {import("../mappers/user/userMapper.js").RegisteredUser} RegisteredUser */
/** @typedef {import("../mappers/user/userMapper.js").EmailVerification} EmailVerification */

/**
 * POST /users : inscription ; 422 (champs) et 409 EMAIL_TAKEN arrivent en ApiError.
 * @param {RegistrationValues} values
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<RegisteredUser>}
 */
export async function registerUser(values, { signal } = {}) {
  return toRegisteredUser(await api.post("/users", toUserCreateDto(values), { signal }));
}

/**
 * Champs du formulaire refuses par le serveur (details d'une 422 de POST /users).
 * @param {import("../../core/api.js").ApiError} error
 * @returns {string[]}
 */
export function rejectedRegistrationFields(error) {
  return toFormFields(/** @type {Record<string, unknown> | null} */ (error.details));
}

/**
 * Attente annoncee par une 429 (`details.retry_after_seconds`, comme l'en-tete Retry-After), null si absente.
 * @param {import("../../core/api.js").ApiError} error
 * @returns {number|null}
 */
export function retryAfterSeconds(error) {
  const seconds = /** @type {Record<string, unknown> | null} */ (error.details)?.retry_after_seconds;
  return typeof seconds === "number" && Number.isFinite(seconds) && seconds > 0 ? seconds : null;
}

/**
 * POST /email-verifications : confirme l'adresse avec le jeton du lien recu par mail.
 * @param {string} token
 * @param {{ signal?: AbortSignal }} [options]
 * @returns {Promise<EmailVerification>}
 */
export async function confirmEmail(token, { signal } = {}) {
  return toEmailVerification(await api.post("/email-verifications", toEmailVerificationCreateDto(token), { signal }));
}
