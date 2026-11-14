import { api, ApiError } from "../../core/api.js";
import { toEventPath, toEventQuery } from "../mappers/eventQueryMapper.js";
import { EventResponseError, readEventResponse, toEventPage } from "../mappers/eventResponseMapper.js";

export { MAX_EVENTS_LIMIT, MAX_EVENTS_OFFSET } from "../mappers/eventQueryMapper.js";

/** @typedef {import("../mappers/eventQueryMapper.js").EventQuery & { signal?: AbortSignal }} EventRequest */

/** Convertit uniquement les erreurs de contrat ; les erreurs HTTP gardent code, details et requestId.
 * @template T
 * @param {(dto: any) => T} mapper
 * @param {any} dto
 * @returns {T}
 */
function decode(mapper, dto) {
  try { return mapper(dto); }
  catch (cause) {
    if (!(cause instanceof EventResponseError)) throw cause;
    throw new ApiError("Reponse evenement invalide", { type: "parse", cause });
  }
}

/** Port de lecture injectable : le service ne depend que de get(), sans DOM ni etat global de page.
 * @param {Pick<typeof api, "get">} [client]
 */
export function createEventService(client = api) {
  /** @param {EventRequest} [options] */
  async function fetchUpcomingEventPage({ signal, ...options } = {}) {
    const query = toEventQuery(options);
    const dto = await client.get(`/events?${query}`, { signal });
    return decode(toEventPage, dto);
  }

  /** L'accueil existant consomme uniquement les elements de la premiere page.
   * @param {EventRequest} [options]
   */
  async function fetchUpcomingEvents(options = {}) {
    return (await fetchUpcomingEventPage(options)).items;
  }

  /** @param {number|string} id
   * @param {{ signal?: AbortSignal }} [options]
   */
  async function fetchEvent(id, { signal } = {}) {
    const path = toEventPath(id);
    if (path === null) throw new ApiError("Event not found", { status: 404, code: "NOT_FOUND" });
    const dto = await client.get(path, { signal });
    return decode(value => readEventResponse(value, String(id)), dto);
  }

  return { fetchUpcomingEventPage, fetchUpcomingEvents, fetchEvent };
}

export const { fetchUpcomingEventPage, fetchUpcomingEvents, fetchEvent } = createEventService();
