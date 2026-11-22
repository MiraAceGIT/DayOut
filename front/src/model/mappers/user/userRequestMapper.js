/** @typedef {import("../../dto/userDto.js").UserCreateDto} UserCreateDto */
/** @typedef {import("../../dto/userDto.js").EmailVerificationCreateDto} EmailVerificationCreateDto */
/** @typedef {import("../../domain/registration.js").RegistrationValues} RegistrationValues */

/**
 * Saisie du formulaire -> corps de POST /users. La confirmation du mot de passe ne part pas ;
 * le nom d'association seulement pour un createur qui n'est pas a titre individuel.
 * @param {RegistrationValues} values
 * @returns {UserCreateDto}
 */
export function toUserCreateDto(values) {
  const creator = values.profile === "creator";
  const individual = creator && values.individual;
  return {
    profile: creator ? "creator" : "participant",
    first_name: values.firstName.trim(),
    last_name: values.lastName.trim(),
    email: values.email.trim().toLowerCase(),
    school_id: Number(values.schoolId),
    password: values.password,
    terms_accepted: values.termsAccepted,
    interests: [...new Set(values.interests)],
    search_radius_km: values.searchRadiusKm,
    organization_name: creator && !individual ? values.organizationName.trim() : null,
    individual,
  };
}

/** @param {string} token @returns {EmailVerificationCreateDto} */
export function toEmailVerificationCreateDto(token) {
  return { token };
}

/** Nom d'un champ de l'API (`details` d'une 422) -> nom du champ du formulaire. */
const FORM_FIELDS = /** @type {const} */ ({
  profile: "profile",
  first_name: "firstName",
  last_name: "lastName",
  email: "email",
  school_id: "schoolId",
  password: "password",
  terms_accepted: "termsAccepted",
  interests: "interests",
  search_radius_km: "searchRadiusKm",
  organization_name: "organizationName",
  individual: "individual",
});

/**
 * Champs refuses par le serveur, renommes comme le formulaire ; un champ inconnu reste sous son nom d'API.
 * @param {Record<string, unknown> | null | undefined} details
 * @returns {string[]}
 */
export function toFormFields(details) {
  return Object.keys(details ?? {}).map((field) => FORM_FIELDS[/** @type {keyof typeof FORM_FIELDS} */ (field)] ?? field);
}
