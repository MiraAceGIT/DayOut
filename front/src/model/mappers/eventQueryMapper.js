import { parseEventId } from "../domain/eventId.js";

export const MAX_EVENTS_LIMIT = 100;
export const MAX_EVENTS_OFFSET = 10000;

/** Parametres de lecture utilises par la liste et la carte publiques.
 * @typedef {object} EventQuery
 * @property {number} [limit]
 * @property {number} [offset]
 * @property {string} [from]
 */

export function normalizeEventLimit(limit = 20) {
  if (!Number.isFinite(limit)) throw new RangeError("Event limit must be a finite number");
  return Math.min(MAX_EVENTS_LIMIT, Math.max(1, Math.trunc(limit)));
}

/** @param {EventQuery} [options] */
export function toEventQuery({ limit, offset = 0, from } = {}) {
  if (!Number.isInteger(offset) || offset < 0 || offset > MAX_EVENTS_OFFSET) {
    throw new RangeError("Event offset must be an integer between 0 and 10000");
  }
  const query = new URLSearchParams({ limit: String(normalizeEventLimit(limit)), offset: String(offset) });
  if (from) query.set("from", from);
  return query;
}

/** @param {number|string} id */
export function toEventPath(id) {
  if (typeof id === "number" && !Number.isSafeInteger(id)) return null;
  const parsed = parseEventId(String(id));
  return parsed === null ? null : `/events/${parsed}`;
}
