import { api } from "../../core/api.js";
import { toEvents } from "../mappers/eventMapper.js";
import { toEventDetail } from "../mappers/eventDetailMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

export const MAX_EVENTS_LIMIT = 100;
export const MAX_EVENTS_OFFSET = 10000;

/** @param {{ limit?: number, offset?: number, from?: string, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEventPage({ limit = 20, offset = 0, from, signal } = {}) {
  const query = new URLSearchParams({
    limit: String(Math.min(limit, MAX_EVENTS_LIMIT)),
    offset: String(offset),
  });
  if (from) query.set("from", from);
  /** @type {EventPageDto} */
  const page = await api.get(`/events?${query}`, { signal });
  return { items: toEvents(page.items), limit: page.limit, offset: page.offset, hasMore: page.has_more };
}

/** Compatibilite avec l'accueil : il ne consomme que les evenements de la premiere page.
 * @param {{ limit?: number, signal?: AbortSignal }} [options]
 */
export async function fetchUpcomingEvents(options = {}) {
  return (await fetchUpcomingEventPage(options)).items;
}

/** @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function fetchEvent(id, { signal } = {}) {
  /** @type {import("../dto/eventDetailDto.js").EventDetailDto} */
  const dto = await api.get(`/events/${encodeURIComponent(id)}`, { signal });
  return toEventDetail(dto);
}
