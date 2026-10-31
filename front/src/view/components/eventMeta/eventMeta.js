import { formatDistance } from "../../../shared/format.js";

export function joinMeta(...parts) {
  return parts.filter(Boolean).join(" · ");
}

export function placeOf(event) {
  return joinMeta(event.venueName, event.city);
}

export function distanceText(distanceKm, { unknown = "" } = {}) {
  return distanceKm === null ? unknown : formatDistance(distanceKm);
}
