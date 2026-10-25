/** Seuil "dernieres places" de la spec. */
export const LAST_PLACES_THRESHOLD = 5;

/** Places restantes ; null si capacite illimitee ou inconnue. */
export function remainingPlaces(capacity, registeredCount) {
  if (capacity === null || capacity === undefined) return null;
  return Math.max(0, Number(capacity) - Number(registeredCount || 0));
}

/** "full" (complet), "last" (dernieres places), "open" ; null si capacite illimitee ou inconnue. */
export function availabilityOf(remaining) {
  if (remaining === null) return null;
  if (remaining === 0) return "full";
  return remaining <= LAST_PLACES_THRESHOLD ? "last" : "open";
}

/** Faux si l'evenement est termine (fin, ou debut a defaut, avant `now`) ; date illisible -> garde. */
export function isUpcoming(event, now = new Date()) {
  const last = Date.parse(event.endAt ?? event.startAt);
  return !(Number.isFinite(last) && last < now.getTime());
}
