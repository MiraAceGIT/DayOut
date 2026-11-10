import { api } from "../../core/api.js";
import { toEvent, toEvents } from "../mappers/eventMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

export const MAX_EVENTS_LIMIT = 100;
export const MAX_EVENTS_OFFSET = 10000;
/** Meme plafond que la regle `q` de l'API (back : EventFindQuery::RULES). */
export const MAX_KEYWORD_LENGTH = 100;

/** @param {{ limit?: number, offset?: number, from?: string, query?: string, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEventPage({ limit = 20, offset = 0, from, query: keyword = "", signal } = {}) {
  const query = new URLSearchParams({
    limit: String(Math.min(limit, MAX_EVENTS_LIMIT)),
    offset: String(offset),
  });
  if (from) query.set("from", from);
  if (keyword.trim()) query.set("q", keyword.trim().slice(0, MAX_KEYWORD_LENGTH));
  /** @type {EventPageDto} */
  const page = await api.get(`/events?${query}`, { signal });
  return {
    items: toEvents(page.items), limit: page.limit, offset: page.offset, hasMore: page.has_more, total: page.total ?? null,
  };
}

/** Compatibilite avec l'accueil : il ne consomme que les evenements de la premiere page.
 * @param {{ limit?: number, query?: string, signal?: AbortSignal }} [options]
 */
export async function fetchUpcomingEvents(options = {}) {
  return (await fetchUpcomingEventPage(options)).items;
}

/** @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function fetchEvent(id, { signal } = {}) {
  /** @type {import("../dto/eventDto.js").EventDto} */
  const dto = await api.get(`/events/${encodeURIComponent(id)}`, { signal });
  return toEvent(dto);
}
