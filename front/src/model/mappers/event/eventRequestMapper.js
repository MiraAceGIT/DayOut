import { cleanKeyword } from "../../domain/keyword.js";
import { toPageQuery } from "../pageQueryMapper.js";

export const MIN_RADIUS_KM = 1;
export const MAX_RADIUS_KM = 20;

/**
 * @typedef {object} EventQuery
 * @property {number} [limit]
 * @property {number} [offset]
 * @property {string} [from]     ISO 8601
 * @property {string} [keyword]
 * @property {{ lat: number, lng: number }} [origin]   centre de recherche geographique
 * @property {number} [radiusKm] rayon en km ; lat, lng et radius vont ensemble
 */

/** @param {EventQuery} [options] */
export function toEventQuery({ limit, offset = 0, from, keyword = "", origin, radiusKm } = {}) {
  const query = toPageQuery({ limit, offset });
  if (from) query.set("from", from);
  const q = cleanKeyword(keyword);
  if (q) query.set("q", q);
  if (origin && Number.isFinite(origin.lat) && Number.isFinite(origin.lng) && Number.isFinite(radiusKm)) {
    const clamped = Math.min(MAX_RADIUS_KM, Math.max(MIN_RADIUS_KM, /** @type {number} */ (radiusKm)));
    query.set("lat", String(origin.lat));
    query.set("lng", String(origin.lng));
    query.set("radius", String(clamped));
  }
  return query;
}

// L'API valide l'id ; l'encoder l'empeche de viser une autre route (`../faq`, `1?x=2`).
/** @param {number|string} id */
export function toEventPath(id) {
  return `/events/${encodeURIComponent(String(id))}`;
}
