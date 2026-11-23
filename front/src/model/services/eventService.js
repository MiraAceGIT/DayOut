import { api } from "../../core/api.js";
import { toEvent, toEvents } from "../mappers/event/eventMapper.js";
import { toEventPath, toEventQuery } from "../mappers/event/eventRequestMapper.js";
import { DEFAULT_PAGE_LIMIT } from "../mappers/pageQueryMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

/** @param {{ limit?: number, offset?: number, from?: string, query?: string,
 *             origin?: { lat: number, lng: number }, radiusKm?: number, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEventPage({
  limit = DEFAULT_PAGE_LIMIT, offset = 0, from, query: keyword = "", origin, radiusKm, signal,
} = {}) {
  const query = toEventQuery({ limit, offset, from, keyword, origin, radiusKm });
  /** @type {EventPageDto} */
  const page = await api.get(`/events?${query}`, { signal });
  return {
    items: toEvents(page.items), limit: page.limit, offset: page.offset, hasMore: page.has_more, total: page.total ?? null,
  };
}

/** @param {{ limit?: number, query?: string, origin?: { lat: number, lng: number }, radiusKm?: number,
 *             signal?: AbortSignal }} [options]
 */
export async function fetchUpcomingEvents(options = {}) {
  return (await fetchUpcomingEventPage(options)).items;
}

/** @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function fetchEvent(id, { signal } = {}) {
  /** @type {import("../dto/eventDto.js").EventDto} */
  const dto = await api.get(toEventPath(id), { signal });
  return toEvent(dto);
}
