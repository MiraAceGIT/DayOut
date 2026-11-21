// Textes de statut de la liste des events (annonces role=status) : fonctions pures, testees sans DOM.
import { pluralize } from "../../shared/format.js";

/** @param {number} count */
const shown = (count) => `${pluralize(count, "événement")} à venir affiché${count > 1 ? "s" : ""}.`;

/**
 * Apres une page chargee.
 * @param {number} count cartes affichees au total
 * @param {{ hasMore: boolean, limitReached?: boolean }} pagination
 */
export function paginationStatus(count, { hasMore, limitReached = false }) {
  const base = count === 0 ? "Aucun événement à venir." : shown(count);
  if (limitReached) return `${base} La limite de consultation est atteinte.`;
  return !hasMore && count > 0 ? `${base} Tous les événements ont été affichés.` : base;
}

/** Apres un echec : les cartes deja chargees restent affichees. @param {number} count */
export function errorStatus(count) {
  return count ? `${pluralize(count, "événement")} déjà affiché${count > 1 ? "s" : ""}.` : "";
}
