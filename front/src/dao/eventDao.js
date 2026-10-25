import { api } from "../core/api.js";
import { toEvents } from "../mappers/eventMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

/** Plafond de `limit` cote back (EventController::index). */
export const MAX_EVENTS_LIMIT = 100;

/** Tries par date mais NON filtres : l'API renvoie aussi les evenements passes (voir isUpcoming). */
export async function fetchUpcomingEvents({ limit = 20, signal } = {}) {
  const query = new URLSearchParams({ limit: String(Math.min(limit, MAX_EVENTS_LIMIT)) });
  /** @type {EventPageDto} */
  const { items } = await api.get(`/api/events?${query}`, { signal });
  return toEvents(items);
}
