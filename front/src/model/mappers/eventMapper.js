import { availabilityOf, remainingPlaces } from "../domain/Event.js";
import { toCategory, toCategoryDto } from "./categoryMapper.js";

/** @typedef {import("../dto/eventDto.js").EventDto} EventDto */

/** Nombre ou chaine numerique -> nombre ; vide/invalide -> null (jamais 0, qui serait une vraie coordonnee). */
function toCoordinate(value) {
  if (value === null || value === undefined || value === "") return null;
  const n = Number(value);
  return Number.isFinite(n) ? n : null;
}

/** EventDto -> evenement du front. Valeurs gardees brutes (pas de coercition) : `formatPrice` traite un `isPaid` absent comme payant. */
export function toEvent(dto) {
  const latitude = toCoordinate(dto.latitude);
  const longitude = toCoordinate(dto.longitude);
  const registeredCount = dto.registered_count ?? 0;
  const remaining = remainingPlaces(dto.capacity, registeredCount);

  return {
    id: dto.id,
    title: dto.title ?? "",
    slug: dto.slug,
    description: dto.description,
    coverImageUrl: dto.cover_image_url ?? null,
    startAt: dto.start_at,
    endAt: dto.end_at,
    venueName: dto.venue_name,
    addressLine: dto.address_line ?? null,
    city: dto.city,
    postalCode: dto.postal_code ?? null,
    priceCents: dto.price_cents,
    isPaid: dto.is_paid,
    currency: dto.currency,
    registeredCount,
    capacity: dto.capacity,
    remainingPlaces: remaining,
    availability: availabilityOf(remaining),
    position: latitude !== null && longitude !== null ? { lat: latitude, lng: longitude } : null,
    externalTicketUrl: dto.external_ticket_url ?? null,
    schoolRestricted: Boolean(dto.school_restricted),
    categories: Array.isArray(dto.categories) ? dto.categories.map(toCategory) : [],
  };
}

/** Liste d'EventDto -> evenements ; tout autre chose qu'un tableau -> []. */
export function toEvents(dtos) {
  return Array.isArray(dtos) ? dtos.map(toEvent) : [];
}

/** Evenement du front -> EventDto (envoi a l'API). Les champs calcules (places restantes, disponibilite) ne repartent pas. */
export function toEventDto(event) {
  return /** @type {EventDto} */ ({
    id: event.id,
    title: event.title,
    slug: event.slug,
    description: event.description,
    cover_image_url: event.coverImageUrl,
    start_at: event.startAt,
    end_at: event.endAt,
    venue_name: event.venueName,
    address_line: event.addressLine,
    city: event.city,
    postal_code: event.postalCode,
    latitude: event.position?.lat ?? null,
    longitude: event.position?.lng ?? null,
    capacity: event.capacity,
    registered_count: event.registeredCount,
    price_cents: event.priceCents,
    currency: event.currency,
    is_paid: event.isPaid,
    external_ticket_url: event.externalTicketUrl,
    school_restricted: event.schoolRestricted,
    categories: event.categories.map(toCategoryDto),
  });
}
