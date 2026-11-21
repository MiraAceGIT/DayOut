import { cleanKeyword } from "../../domain/keyword.js";
import { toPageQuery } from "../pageQueryMapper.js";

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
  const q = cleanKeyword(keyword);
  if (q) query.set("q", q);
  return query;
}

/** Chemin de GET /events/{id}. L'API valide l'identifiant (404 s'il est invalide) ; il est seulement encode
 * pour rester un seul segment du chemin (`../faq` ou `1?x=2` ne visent pas une autre route).
 * @param {number|string} id */
export function toEventPath(id) {
  return `/events/${encodeURIComponent(String(id))}`;
}
