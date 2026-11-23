// Textes du formulaire d'inscription. Les codes viennent de model/domain/registration.js ;
// "rejected" = champ refuse par le serveur sans regle cote navigateur (ex. ecole desactivee entre-temps).

/** @type {Record<string, Record<string, string>>} */
const FIELD_MESSAGES = {
  firstName: { required: "Indique ton prénom.", too_long: "Ton prénom doit faire 80 caractères au plus." },
  lastName: { required: "Indique ton nom.", too_long: "Ton nom doit faire 80 caractères au plus." },
  email: {
    required: "Indique l'adresse e-mail fournie par ton école.",
    invalid: "Cette adresse e-mail n'est pas valide (exemple : prenom.nom@ecole.fr).",
    too_long: "Cette adresse e-mail est trop longue.",
    domain_not_allowed: "Cette adresse n'appartient à aucune école partenaire : utilise l'e-mail fourni par ton école.",
    school_mismatch: "Cette adresse ne correspond pas à l'école choisie.",
    taken: "Un compte existe déjà avec cette adresse e-mail. S'il n'est pas encore confirmé, ouvre le lien reçu par mail ; passé 24 h, tu pourras t'inscrire de nouveau.",
    rejected: "Cette adresse n'est pas acceptée : vérifie qu'elle correspond bien à ton école.",
  },
  schoolId: {
    required: "Choisis ton école.",
    rejected: "Cette école n'accepte plus d'inscriptions : recharge la page et choisis-en une autre.",
  },
  password: {
    required: "Choisis un mot de passe.",
    too_short: "Ton mot de passe doit faire au moins 8 caractères.",
    too_long: "Ton mot de passe est trop long (72 octets au plus).",
    missing_uppercase: "Ajoute au moins une lettre majuscule.",
    missing_digit: "Ajoute au moins un chiffre.",
    rejected: "Ce mot de passe n'est pas accepté : 8 caractères minimum, dont une majuscule et un chiffre.",
  },
  passwordConfirm: {
    required: "Confirme ton mot de passe.",
    mismatch: "Les deux mots de passe ne sont pas identiques.",
  },
  termsAccepted: { required: "Tu dois accepter les conditions générales d'utilisation." },
  interests: {
    required: "Choisis au moins un centre d'intérêt.",
    rejected: "Une catégorie choisie n'existe plus : recharge la page puis choisis à nouveau.",
  },
  searchRadiusKm: { out_of_range: "Choisis un rayon entre 1 et 20 km." },
  organizationName: {
    required: "Indique le nom de ton association, club ou BDE, ou coche « à titre individuel ».",
    too_long: "Ce nom doit faire 120 caractères au plus.",
  },
};

const DEFAULT_MESSAGE = "Vérifie ce champ.";

/** @param {string} field @param {string} code */
export function fieldMessage(field, code) {
  return FIELD_MESSAGES[field]?.[code] ?? FIELD_MESSAGES[field]?.rejected ?? DEFAULT_MESSAGE;
}

/** @param {Record<string, string>} errors champ -> code @returns {Record<string, string>} champ -> texte */
export function fieldMessages(errors) {
  return Object.fromEntries(Object.entries(errors).map(([field, code]) => [field, fieldMessage(field, code)]));
}

const STRENGTH_LABELS = ["", "Force : faible", "Force : moyenne", "Force : bonne", "Force : excellente"];

/** @param {number} level 0 a 4 */
export function passwordStrengthLabel(level) {
  return STRENGTH_LABELS[level] ?? "";
}

/** @param {number} km */
export function radiusLabel(km) {
  return `${km} km`;
}

export const REGISTER_LOADING = "Chargement du formulaire…";
export const REGISTER_SUBMITTING = "Création de ton compte…";
export const REGISTER_INVALID = "Certains champs sont à corriger : ils sont signalés sous chaque champ.";

/** @param {string} email */
export function successText(email) {
  return `Nous avons envoyé un lien de confirmation à ${email}. Ouvre-le pour activer ton compte : il est valable 24 h et ne sert qu'une fois.`;
}

export const SUCCESS_CREATOR =
  "Une fois ton adresse confirmée, ta demande de compte créateur sera examinée par un administrateur. Tu pourras publier des événements dès qu'elle sera validée.";

export const DEMO_LINK_INTRO = "Mode démo (aucun e-mail n'est réellement envoyé) : ";
export const DEMO_LINK_LABEL = "ouvrir le lien de confirmation";

/** @param {string|null} schoolName */
export function creatorSchoolText(schoolName) {
  return schoolName ?? "celle choisie plus haut";
}
