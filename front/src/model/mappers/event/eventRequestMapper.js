import { cleanKeyword } from "../../domain/keyword.js";
import { toPageQuery } from "../pageQueryMapper.js";

/**
 * @typedef {object} EventQuery
 * @property {number} [limit]
 * @property {number} [offset]
 * @property {string} [from]     ISO 8601
 * @property {string} [keyword]
 */

/** @param {EventQuery} [options] */
export function toEventQuery({ limit, offset = 0, from, keyword = "" } = {}) {
  const query = toPageQuery({ limit, offset });
  if (from) query.set("from", from);
  const q = cleanKeyword(keyword);
  if (q) query.set("q", q);
  return query;
}

// L'API valide l'id ; l'encoder l'empeche de viser une autre route (`../faq`, `1?x=2`).
/** @param {number|string} id */
export function toEventPath(id) {
  return `/events/${encodeURIComponent(String(id))}`;
}
