export const CONFIRM_LOADING = "Confirmation de ton adresse…";
export const REGISTER = "Créer un compte";
export const REGISTER_AGAIN = "Refaire mon inscription";

/** @typedef {"confirmed" | "invalid" | "expired" | "used"} ConfirmOutcome */

/** @type {Record<ConfirmOutcome, { title: string, text: string }>} */
const OUTCOMES = {
  confirmed: {
    title: "Ton adresse est confirmée",
    text: "Ton compte DayOut est actif : tu peux te connecter et rejoindre des événements dès maintenant.",
  },
  invalid: {
    title: "Lien de confirmation invalide",
    text: "Ce lien est incomplet ou n'existe pas. Ouvre le lien tel qu'il figure dans l'e-mail reçu, sans le modifier.",
  },
  expired: {
    title: "Lien de confirmation expiré",
    // Le back remplace un compte non confirme dont le lien a expire : refaire l'inscription renvoie un lien.
    text: "Ce lien n'était valable que 24 h : ton adresse n'a pas été confirmée. Refais ton inscription avec la même adresse pour recevoir un nouveau lien.",
  },
  used: {
    title: "Lien déjà utilisé",
    text: "Ce lien a déjà servi : ton adresse est confirmée, tu peux te connecter.",
  },
};

const CREATOR_PENDING =
  " Ta demande de compte créateur est maintenant entre les mains d'un administrateur : tu pourras publier des événements dès qu'elle sera validée.";

/** @param {ConfirmOutcome} outcome @param {{ creatorPending?: boolean }} [options] */
export function confirmOutcome(outcome, { creatorPending = false } = {}) {
  const { title, text } = OUTCOMES[outcome];
  return { title, text: outcome === "confirmed" && creatorPending ? text + CREATOR_PENDING : text };
}
