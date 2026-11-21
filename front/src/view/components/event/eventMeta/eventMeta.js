import { formatDistance } from "../../../../shared/format.js";

export function joinMeta(...parts) {
  return parts.filter(Boolean).join(" · ");
}

export function distanceText(distanceKm, { unknown = "" } = {}) {
  return distanceKm === null ? unknown : formatDistance(distanceKm);
}
