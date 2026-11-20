import { MAX_PAGE_LIMIT, MAX_PAGE_OFFSET } from "../../core/apiLimits.js";

export const DEFAULT_PAGE_LIMIT = 20;

/** `limit` ramene dans [1, MAX_PAGE_LIMIT] : l'API refuserait (422) une valeur hors bornes.
 * @param {number} [limit] */
export function normalizePageLimit(limit = DEFAULT_PAGE_LIMIT) {
  if (!Number.isFinite(limit)) throw new RangeError("Page limit must be a finite number");
  return Math.min(MAX_PAGE_LIMIT, Math.max(1, Math.trunc(limit)));
}

/**
 * `limit` / `offset` d'une liste paginee de l'API (PageRequest cote back), communs a toutes les ressources.
 * Un offset hors bornes est une erreur du code appelant, pas une saisie : RangeError.
 * @param {{ limit?: number, offset?: number }} [page]
 */
export function toPageQuery({ limit, offset = 0 } = {}) {
  if (!Number.isInteger(offset) || offset < 0 || offset > MAX_PAGE_OFFSET) {
    throw new RangeError(`Page offset must be an integer between 0 and ${MAX_PAGE_OFFSET}`);
  }
  return new URLSearchParams({ limit: String(normalizePageLimit(limit)), offset: String(offset) });
}
