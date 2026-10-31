import { api } from "../../core/api.js";
import { toEvents } from "../mappers/eventMapper.js";

/** @typedef {import("../dto/eventDto.js").EventPageDto} EventPageDto */

export const MAX_EVENTS_LIMIT = 100;

/** @param {{ limit?: number, signal?: AbortSignal }} [options] */
export async function fetchUpcomingEvents({ limit = 20, signal } = {}) {
  const query = new URLSearchParams({ limit: String(Math.min(limit, MAX_EVENTS_LIMIT)) });
  /** @type {EventPageDto} */
  const { items } = await api.get(`/events?${query}`, { signal });
  return toEvents(items);
}
