import { pluralize } from "../../shared/format.js";

/** @param {number} count */
const shown = (count) => `${pluralize(count, "événement")} à venir affiché${count > 1 ? "s" : ""}.`;

/**
 * @param {number} count
 * @param {{ hasMore: boolean, limitReached?: boolean }} pagination
 */
export function paginationStatus(count, { hasMore, limitReached = false }) {
  const base = count === 0 ? "Aucun événement à venir." : shown(count);
  if (limitReached) return `${base} La limite de consultation est atteinte.`;
  return !hasMore && count > 0 ? `${base} Tous les événements ont été affichés.` : base;
}

/** @param {number} count */
export function errorStatus(count) {
  return count ? `${pluralize(count, "événement")} déjà affiché${count > 1 ? "s" : ""}.` : "";
}
