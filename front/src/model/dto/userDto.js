// Miroir de back/src/Model/Dto/User (UserCreateDto, RegisteredUserDto, EmailVerificationDto).

/**
 * Corps de POST /users (UserCreateDto::RULES).
 * @typedef {object} UserCreateDto
 * @property {"participant" | "creator"} profile
 * @property {string} first_name
 * @property {string} last_name
 * @property {string} email
 * @property {number} school_id
 * @property {string} password
 * @property {boolean} terms_accepted
 * @property {string[]} interests              - slugs de categories
 * @property {number} search_radius_km
 * @property {string|null} organization_name   - createur, sauf a titre individuel
 * @property {boolean} individual
 */

/**
 * Reponse de POST /users.
 * @typedef {object} RegisteredUserDto
 * @property {number} id
 * @property {string} email
 * @property {string} first_name
 * @property {string} role
 * @property {string} creator_status
 * @property {boolean} email_verified
 * @property {string|null} demo_confirmation_url  - mode demo du back seulement
 */

/**
 * Corps de POST /email-verifications.
 * @typedef {object} EmailVerificationCreateDto
 * @property {string} token
 */

/**
 * Reponse de POST /email-verifications.
 * @typedef {object} EmailVerificationDto
 * @property {boolean} email_verified
 * @property {string} creator_status
 */

export {};
