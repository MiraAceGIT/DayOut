import { api } from "../core/api.js";
import { toCategory } from "./category.js";

/** Plafond de `limit` cote back (EventController::index). */
export const MAX_EVENTS_LIMIT = 100;

/** Seuil "dernieres places" de la spec. */
export const LAST_PLACES_THRESHOLD = 5;

/** Nombre ou chaine numerique -> nombre ; vide/invalide -> null (jamais 0, qui serait une vraie coordonnee). */
function toCoordinate(value) {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

/** Places restantes ; null si capacite illimitee ou inconnue. */
function remainingPlaces(capacity, registeredCount) {
  if (capacity === null || capacity === undefined) return null;
  return Math.max(0, Number(capacity) - Number(registeredCount || 0));
}

/** "full" (complet), "last" (dernieres places), "open" ; null si capacite illimitee ou inconnue. */
function availabilityOf(remaining) {
  if (remaining === null) return null;
  if (remaining === 0) return "full";
  return remaining <= LAST_PLACES_THRESHOLD ? "last" : "open";
}

/** Valeurs gardees brutes (pas de coercition) : `formatPrice` traite un `isPaid` absent comme payant. */
export function toEvent(raw) {
  const latitude = toCoordinate(raw.latitude);
  const longitude = toCoordinate(raw.longitude);
  const registeredCount = raw.registered_count ?? 0;
  const remaining = remainingPlaces(raw.capacity, registeredCount);

  return {
    id: raw.id,
    title: raw.title ?? "",
    startAt: raw.start_at,
    endAt: raw.end_at,
    venueName: raw.venue_name,
    city: raw.city,
    description: raw.description,
    priceCents: raw.price_cents,
    isPaid: raw.is_paid,
    currency: raw.currency,
    registeredCount,
    capacity: raw.capacity,
    remainingPlaces: remaining,
    availability: availabilityOf(remaining),
    position: latitude !== null && longitude !== null ? { lat: latitude, lng: longitude } : null,
    categories: Array.isArray(raw.categories) ? raw.categories.map(toCategory) : [],
  };
}

/** Faux si l'evenement est termine (fin, ou debut a defaut, avant `now`) ; date illisible -> garde. */
export function isUpcoming(event, now = new Date()) {
  const last = Date.parse(event.endAt ?? event.startAt);
  return !(Number.isFinite(last) && last < now.getTime());
}

/** Tries par date mais NON filtres : l'API renvoie aussi les evenements passes (voir isUpcoming). */
export async function fetchUpcomingEvents({ limit = 20, signal } = {}) {
  const query = new URLSearchParams({ limit: String(Math.min(limit, MAX_EVENTS_LIMIT)) });
  const { items } = await api.get(`/api/events?${query}`, { signal });
  return Array.isArray(items) ? items.map(toEvent) : [];
}
