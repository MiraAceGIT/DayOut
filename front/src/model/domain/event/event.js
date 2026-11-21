/** @typedef {{ slug: string, label: string }} Category */

/**
 * @typedef {object} Creator
 * @property {number} id
 * @property {string} displayName
 * @property {string|null} description
 * @property {string|null} avatarUrl
 * @property {string|null} schoolName
 */

/**
 * Evenement tel que le front le manipule (construit par model/mappers/event/eventMapper.js depuis EventDto).
 * @typedef {object} Event
 * @property {number} id
 * @property {string} title
 * @property {string} slug
 * @property {string} description
 * @property {string|null} coverImageUrl
 * @property {string} startAt              ISO 8601
 * @property {string} endAt                ISO 8601
 * @property {string|null} venueName
 * @property {string|null} addressLine
 * @property {string|null} city
 * @property {string|null} postalCode
 * @property {number} priceCents
 * @property {boolean} isPaid
 * @property {string} currency
 * @property {number} registeredCount
 * @property {number|null} capacity        null = illimitee
 * @property {number|null} remainingPlaces null = illimitee
 * @property {Availability|null} availability null = illimitee
 * @property {{ lat: number, lng: number }|null} position
 * @property {string|null} externalTicketUrl
 * @property {boolean} schoolRestricted
 * @property {string} status
 * @property {Category[]} categories
 * @property {Creator|null} creator
 */

/** @typedef {"full"|"last"|"open"} Availability */

export const LAST_PLACES_THRESHOLD = 5;

/** Capacite null = illimitee ; ne jamais annoncer un nombre negatif en cas de depassement. */
export function remainingPlaces(capacity, registeredCount) {
  if (capacity === null || capacity === undefined) return null;
  return Math.max(0, Number(capacity) - Number(registeredCount || 0));
}

/** @param {number|null} remaining @returns {Availability|null} */
export function availabilityOf(remaining) {
  if (remaining === null) return null;
  if (remaining === 0) return "full";
  return remaining <= LAST_PLACES_THRESHOLD ? "last" : "open";
}

export function isUpcoming(event, now = new Date()) {
  const last = Date.parse(event.endAt ?? event.startAt);
  return !(Number.isFinite(last) && last < now.getTime());
}
