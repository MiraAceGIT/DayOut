import { distanceKm, isLatLng } from "../../../shared/geo.js";
import { isUpcoming } from "./event.js";
import { matchesCriteria } from "./eventFilters.js";

/** @typedef {import("./event.js").Event} Event */
/** Resultat de recherche : l'evenement et sa distance au point choisi (null si l'un des deux est sans position).
 * @typedef {{ event: Event, distanceKm: number|null }} SearchItem */

/** @returns {(event: Event) => SearchItem} */
export function measureFrom(origin) {
  return (event) => ({
    event,
    distanceKm: isLatLng(event.position) && isLatLng(origin) ? distanceKm(origin, event.position) : null,
  });
}

/** Un evenement sans position reste dans la liste : on ne sait pas s'il est dans le cercle. */
export function withinRadius(radiusKm) {
  return ({ distanceKm: km }) => km === null || !(km > radiusKm);
}

export function countByPosition(items) {
  const inCircle = items.filter((item) => item.distanceKm !== null).length;
  return { inCircle, withoutPosition: items.length - inCircle };
}

const startTime = (item) => Date.parse(item.event.startAt) || 0;

/**
 * @param {Event[]} events
 * @param {{ origin?: { lat: number, lng: number }, radiusKm?: number, now?: Date, [criterion: string]: unknown }} [options]
 * @returns {{ items: SearchItem[], inCircle: number, withoutPosition: number }}
 */
export function searchEvents(events, { origin, radiusKm, now = new Date(), ...criteria } = {}) {
  const matches = matchesCriteria({ ...criteria, now });
  const items = events
    .filter((event) => isUpcoming(event, now) && matches(event))
    .map(measureFrom(origin))
    .filter(withinRadius(radiusKm))
    .sort((a, b) => startTime(a) - startTime(b));
  return { items, ...countByPosition(items) };
}
