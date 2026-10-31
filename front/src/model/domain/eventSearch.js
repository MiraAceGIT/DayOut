import { distanceKm, isLatLng } from "../../shared/geo.js";
import { isUpcoming } from "./Event.js";
import { matchesCriteria } from "./eventFilters.js";

/** Distance au point choisi ; null si l'evenement (ou le point) n'a pas de coordonnees. */
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

/** Seuls les evenements dessinables comptent dans le cercle. */
export function countByPosition(items) {
  const inCircle = items.filter((item) => item.distanceKm !== null).length;
  return { inCircle, withoutPosition: items.length - inCircle };
}

const startTime = (item) => Date.parse(item.event.startAt) || 0;

/**
 * @param {object[]} events evenements du front (toEvent)
 * @param {{ origin?: { lat: number, lng: number }, radiusKm?: number, now?: Date, [criterion: string]: unknown }} [options]
 *        criteres de matchesCriteria en plus (weekend, free, available, categories, query)
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
