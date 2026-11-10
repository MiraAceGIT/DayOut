export const LAST_PLACES_THRESHOLD = 5;

/** Capacite null = illimitee ; ne jamais annoncer un nombre negatif en cas de depassement. */
export function remainingPlaces(capacity, registeredCount) {
  if (capacity === null || capacity === undefined) return null;
  return Math.max(0, Number(capacity) - Number(registeredCount || 0));
}

export function availabilityOf(remaining) {
  if (remaining === null) return null;
  if (remaining === 0) return "full";
  return remaining <= LAST_PLACES_THRESHOLD ? "last" : "open";
}

export function isUpcoming(event, now = new Date()) {
  const last = Date.parse(event.endAt ?? event.startAt);
  return !(Number.isFinite(last) && last < now.getTime());
}
