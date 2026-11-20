import { api, ApiError } from "../../core/api.js";
import { toEvent, toEvents } from "../mappers/eventMapper.js";
import { toEventPath, toEventQuery } from "../mappers/eventRequestMapper.js";
import { DEFAULT_PAGE_LIMIT } from "../mappers/pageQueryMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

/** @param {{ limit?: number, offset?: number, from?: string, query?: string, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEventPage({ limit = DEFAULT_PAGE_LIMIT, offset = 0, from, query: keyword = "", signal } = {}) {
  const query = toEventQuery({ limit, offset, from, keyword });
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
