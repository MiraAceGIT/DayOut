import { api, ApiError } from "../../core/api.js";
import { toEvent, toEvents } from "../mappers/eventMapper.js";
import { toEventPath, toEventQuery } from "../mappers/eventQueryMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

export { MAX_EVENTS_LIMIT, MAX_EVENTS_OFFSET } from "../mappers/eventQueryMapper.js";

/** @param {{ limit?: number, offset?: number, from?: string, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEventPage({ limit = 20, offset = 0, from, signal } = {}) {
  const query = toEventQuery({ limit, offset, from });
  /** @type {EventPageDto} */
  const page = await api.get(`/events?${query}`, { signal });
  return {
    items: toEvents(page.items), limit: page.limit, offset: page.offset, hasMore: page.has_more, total: page.total ?? null,
  };
}

/** Compatibilite avec l'accueil : il ne consomme que les evenements de la premiere page.
 * @param {{ limit?: number, signal?: AbortSignal }} [options]
 */
export async function fetchUpcomingEvents(options = {}) {
  return (await fetchUpcomingEventPage(options)).items;
}

/** Un identifiant invalide ne part pas sur le reseau : meme 404 que l'API.
 * @param {number|string} id
 * @param {{ signal?: AbortSignal }} [options]
 */
export async function fetchEvent(id, { signal } = {}) {
  const path = toEventPath(id);
  if (path === null) throw new ApiError("Event not found", { status: 404, code: "NOT_FOUND" });
  /** @type {import("../dto/eventDto.js").EventDto} */
  const dto = await api.get(path, { signal });
  return toEvent(dto);
}
