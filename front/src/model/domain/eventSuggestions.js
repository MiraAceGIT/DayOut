import { searchEvents } from "./eventSearch.js";

export const SUGGESTIONS_LIMIT = 3;

/** Selection publique parmi les evenements deja charges ; sans personnalisation ni nouvelle requete. */
export function suggestEvents(events, { now = new Date(), limit = SUGGESTIONS_LIMIT } = {}) {
  return searchEvents(events, { now, available: true }).items
    .slice(0, Math.max(0, limit))
    .map(item => item.event);
}
