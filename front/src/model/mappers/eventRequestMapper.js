import { MAX_KEYWORD_LENGTH } from "../../core/apiLimits.js";
import { parseEventId } from "../domain/eventId.js";
import { toPageQuery } from "./pageQueryMapper.js";

/** Criteres de lecture de la liste et de la carte publiques, traduits en parametres de GET /events.
 * @typedef {object} EventQuery
 * @property {number} [limit]
 * @property {number} [offset]
 * @property {string} [from]     ISO 8601 : evenements finissant apres cet instant (defaut API : maintenant)
 * @property {string} [keyword]  recherche `q`, coupee a MAX_KEYWORD_LENGTH ; vide = pas de filtre
 */

/** @param {EventQuery} [options] */
export function toEventQuery({ limit, offset = 0, from, keyword = "" } = {}) {
  const query = toPageQuery({ limit, offset });
  if (from) query.set("from", from);
  const q = keyword.trim().slice(0, MAX_KEYWORD_LENGTH);
  if (q) query.set("q", q);
  return query;
}

/** Chemin de GET /events/{id}, ou null si l'identifiant est invalide (meme 404 que l'API, sans requete).
 * @param {number|string} id */
export function toEventPath(id) {
  if (typeof id === "number" && !Number.isSafeInteger(id)) return null;
  const parsed = parseEventId(String(id));
  return parsed === null ? null : `/events/${parsed}`;
}
