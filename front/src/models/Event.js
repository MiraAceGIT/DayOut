import { api } from "../core/api.js";

/** Valeurs gardees brutes (pas de coercition) : `formatPrice` traite un `isPaid` absent comme payant. */
export function toEvent(raw) {
  return {
    id: raw.id,
    title: raw.title ?? "",
    startAt: raw.start_at,
    city: raw.city,
    description: raw.description,
    priceCents: raw.price_cents,
    isPaid: raw.is_paid,
    currency: raw.currency,
    registeredCount: raw.registered_count ?? 0,
    capacity: raw.capacity,
  };
}

export async function fetchUpcomingEvents({ limit = 20, signal } = {}) {
  const query = new URLSearchParams({ limit: String(limit) });
  const { items } = await api.get(`/api/events?${query}`, { signal });
  return Array.isArray(items) ? items.map(toEvent) : [];
}
