// Regles du formulaire d'inscription, miroir de UserCreateDto (back) : le serveur les reverifie toutes.
// Les erreurs sont des codes ; leurs textes sont dans view/user/registerMessages.js.

/** @typedef {import("../mappers/schoolMapper.js").School} School */

/**
 * Saisie du formulaire, en camelCase (lue par RegisterView).
 * @typedef {object} RegistrationValues
 * @property {"participant" | "creator"} profile
 * @property {string} firstName
 * @property {string} lastName
 * @property {string} email
 * @property {string} schoolId          - valeur du <select>, "" si aucune ecole
 * @property {string} password
 * @property {string} passwordConfirm   - jamais envoyee a l'API
 * @property {boolean} termsAccepted
 * @property {string[]} interests       - slugs
 * @property {number} searchRadiusKm
 * @property {string} organizationName
 * @property {boolean} individual
 */

/** @typedef {Partial<Record<keyof RegistrationValues, string>>} RegistrationErrors champ -> code d'erreur */

export const NAME_MAX_LENGTH = 80;
export const EMAIL_MAX_LENGTH = 190;
export const ORGANIZATION_MAX_LENGTH = 120;
export const PASSWORD_MIN_LENGTH = 8;
// bcrypt ignore ce qui depasse 72 octets : le back refuse, le front aussi.
export const PASSWORD_MAX_BYTES = 72;
export const RADIUS_MIN_KM = 1;
export const RADIUS_MAX_KM = 20;
export const DEFAULT_RADIUS_KM = 3;
export const PASSWORD_STRENGTH_MAX = 4;

// Volontairement simple : le back valide aussi (FILTER_VALIDATE_EMAIL).
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** @param {string} email @returns {string} domaine en minuscules, "" sans @ */
export function emailDomain(email) {
  const at = email.trim().lastIndexOf("@");
  return at < 0 ? "" : email.trim().slice(at + 1).toLowerCase();
}

/** @param {School[]} schools @param {string} email @returns {School|null} */
export function schoolForEmail(schools, email) {
  const domain = emailDomain(email);
  return domain === "" ? null : schools.find((school) => school.emailDomain === domain) ?? null;
}

/** @param {string} password @returns {string|null} premier manque, dans l'ordre des consignes */
export function passwordIssue(password) {
  if (password === "") return "required";
  if ([...password].length < PASSWORD_MIN_LENGTH) return "too_short";
  if (new TextEncoder().encode(password).length > PASSWORD_MAX_BYTES) return "too_long";
  if (!/\p{Lu}/u.test(password)) return "missing_uppercase";
  if (!/\d/.test(password)) return "missing_digit";
  return null;
}

/**
 * Force indicative pour la jauge : 0 (vide), 1 tant que les regles de passwordIssue ne sont pas remplies,
 * puis +1 pour 12 caracteres, minuscules ET majuscules ET chiffres, un symbole (PASSWORD_STRENGTH_MAX au plus).
 * @param {string} password
 */
export function passwordStrength(password) {
  if (password === "") return 0;
  if (passwordIssue(password) !== null) return 1;
  let score = 1;
  if ([...password].length >= 12) score++;
  if (/\p{Ll}/u.test(password)) score++;
  if (/[^\p{L}\d]/u.test(password)) score++;
  return Math.min(score, PASSWORD_STRENGTH_MAX);
}

/** @param {string} value @param {number} max */
function textIssue(value, max) {
  if (value.trim() === "") return "required";
  return [...value.trim()].length > max ? "too_long" : null;
}

/**
 * @param {RegistrationValues} values
 * @param {School[]} schools ecoles actives
 * @returns {RegistrationErrors}
 */
export function validateRegistration(values, schools) {
  /** @type {RegistrationErrors} */
  const errors = {};
  const set = (/** @type {keyof RegistrationValues} */ field, /** @type {string|null} */ code) => {
    if (code) errors[field] = code;
  };

  // Ordre du formulaire : le premier champ en erreur recoit le focus a l'envoi.
  set("lastName", textIssue(values.lastName, NAME_MAX_LENGTH));
  set("firstName", textIssue(values.firstName, NAME_MAX_LENGTH));

  const email = values.email.trim();
  const school = schools.find((s) => String(s.id) === values.schoolId) ?? null;
  if (email === "") set("email", "required");
  else if (email.length > EMAIL_MAX_LENGTH) set("email", "too_long");
  else if (!EMAIL_PATTERN.test(email)) set("email", "invalid");
  else if (schoolForEmail(schools, email) === null) set("email", "domain_not_allowed");
  else if (school !== null && school.emailDomain !== emailDomain(email)) set("email", "school_mismatch");

  if (school === null) set("schoolId", "required");

  if (values.profile === "creator" && !values.individual) {
    set("organizationName", textIssue(values.organizationName, ORGANIZATION_MAX_LENGTH));
  }

  set("password", passwordIssue(values.password));
  if (values.passwordConfirm === "") set("passwordConfirm", "required");
  else if (values.passwordConfirm !== values.password) set("passwordConfirm", "mismatch");

  if (values.interests.length === 0) set("interests", "required");
  const radius = values.searchRadiusKm;
  if (!Number.isInteger(radius) || radius < RADIUS_MIN_KM || radius > RADIUS_MAX_KM) set("searchRadiusKm", "out_of_range");
  if (!values.termsAccepted) set("termsAccepted", "required");
  return errors;
}
