import { api } from "../core/api.js";
import { toCategory } from "./category.js";

/** Plafond de `limit` cote back (EventController::index). */
export const MAX_EVENTS_LIMIT = 100;

/** Nombre ou chaine numerique -> nombre ; vide/invalide -> null (jamais 0, qui serait une vraie coordonnee). */
function toCoordinate(value) {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

/** Valeurs gardees brutes (pas de coercition) : `formatPrice` traite un `isPaid` absent comme payant. */
export function toEvent(raw) {
  const latitude = toCoordinate(raw.latitude);
  const longitude = toCoordinate(raw.longitude);
  const categories = Array.isArray(raw.categories) ? raw.categories.map(toCategory) : [];

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
    registeredCount: raw.registered_count ?? 0,
    capacity: raw.capacity,
    position: latitude !== null && longitude !== null ? { lat: latitude, lng: longitude } : null,
    categories,
    // Couleur (carre, marqueur) : premiere categorie de la charte, sinon la premiere. L'API trie par libelle,
    // donc "premiere" seule serait alphabetique (une soiree "BDE / Assoc" + "Soiree" sortirait grise).
    category: categories.find((c) => c.key) ?? categories[0] ?? null,
  };
}

/** Places restantes ; null si capacite illimitee ou inconnue. */
export function remainingPlaces(event) {
  if (event.capacity === null || event.capacity === undefined) return null;
  return Math.max(0, Number(event.capacity) - Number(event.registeredCount || 0));
}

export async function fetchUpcomingEvents({ limit = 20, signal } = {}) {
  const query = new URLSearchParams({ limit: String(Math.min(limit, MAX_EVENTS_LIMIT)) });
  const { items } = await api.get(`/api/events?${query}`, { signal });
  return Array.isArray(items) ? items.map(toEvent) : [];
}
