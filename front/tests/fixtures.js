// Donnees partagees par les tests : un EventDto tel que le renvoie l'API, et son evenement du front.
import { toEvent } from "../src/model/mappers/eventMapper.js";

/** EventDto complet (GET /events/1), surcharges par `overrides`. */
export function eventDto(overrides = {}) {
  return {
    id: 1,
    title: "Soirée de rentrée",
    slug: "soiree-rentree",
    description: "DJ set",
    cover_image_url: null,
    start_at: "2026-11-07T20:00:00+00:00",
    end_at: "2026-11-08T02:00:00+00:00",
    venue_name: "Le Rooftop",
    address_line: "28 rue Notre-Dame-des-Victoires",
    city: "Paris",
    postal_code: "75002",
    latitude: 48.8689,
    longitude: 2.341,
    capacity: 250,
    registered_count: 12,
    price_cents: 1500,
    currency: "EUR",
    is_paid: true,
    external_ticket_url: null,
    school_restricted: false,
    categories: [{ slug: "soiree", label: "Soirée" }],
    ...overrides,
  };
}

export const event = (overrides = {}) => toEvent(eventDto(overrides));

/** Espaces insecables (fr-FR : "15 €", "0,6 km") -> espace simple : assertions independantes d'ICU. */
export const spaces = (text) => text.replace(/\s/g, " ");
