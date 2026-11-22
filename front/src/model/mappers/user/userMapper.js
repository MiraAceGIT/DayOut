/** @typedef {import("../../dto/userDto.js").RegisteredUserDto} RegisteredUserDto */
/** @typedef {import("../../dto/userDto.js").EmailVerificationDto} EmailVerificationDto */

/**
 * @typedef {object} RegisteredUser
 * @property {number} id
 * @property {string} email
 * @property {string} firstName
 * @property {string} role
 * @property {string} creatorStatus
 * @property {boolean} emailVerified
 * @property {string|null} demoConfirmationUrl
 */

/**
 * @typedef {object} EmailVerification
 * @property {boolean} emailVerified
 * @property {boolean} creatorPending   - demande createur en attente d'un administrateur
 */

/** @param {RegisteredUserDto} dto @returns {RegisteredUser} */
export function toRegisteredUser(dto) {
  return {
    id: dto.id,
    email: String(dto.email ?? ""),
    firstName: String(dto.first_name ?? ""),
    role: String(dto.role ?? ""),
    creatorStatus: String(dto.creator_status ?? "none"),
    emailVerified: dto.email_verified === true,
    demoConfirmationUrl: typeof dto.demo_confirmation_url === "string" ? dto.demo_confirmation_url : null,
  };
}

/** @param {EmailVerificationDto} dto @returns {EmailVerification} */
export function toEmailVerification(dto) {
  return {
    emailVerified: dto.email_verified === true,
    creatorPending: dto.creator_status === "pending",
  };
}
