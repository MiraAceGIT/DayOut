import { formatDistance } from "../../../shared/format.js";

/** Parties non vides separees par " · " (ligne de details des cartes et de la bulle). */
export function joinMeta(...parts) {
  return parts.filter(Boolean).join(" · ");
}

/** "Lieu · Ville". */
export function placeOf(event) {
  return joinMeta(event.venueName, event.city);
}

/** "0,6 km" ; distance inconnue (pas de coordonnees) -> `unknown`. */
export function distanceText(distanceKm, { unknown = "" } = {}) {
  return distanceKm === null ? unknown : formatDistance(distanceKm);
}
