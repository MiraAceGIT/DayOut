// Textes de la page detail, calcules depuis l'event normalise : fonction pure, testee sans DOM.
// EventDetailView ne fait que les poser dans la page.
import { formatEventWhen, formatPrice, formatRemainingPlaces, pluralize } from "../../shared/format.js";

/** @typedef {import("../../model/domain/event.js").Event} Event */

/** "Garage ISEP" -> "GI" : deux premieres initiales, pour l'avatar sans image. @param {string} name */
export function initials(name) {
  return name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join("").toUpperCase();
}

/** @param {Event} event */
export function presentEventDetail(event) {
  const address = [event.addressLine, [event.postalCode, event.city].filter(Boolean).join(" ")].filter(Boolean).join(", ");
  const place = event.venueName || event.city || "Lieu à préciser";
  const unlimited = event.capacity === null;
  const creatorName = event.creator?.displayName ?? "";

  return {
    documentTitle: `${event.title} — DayOut`,
    title: event.title,
    place,
    address: address || "Adresse à préciser",
    city: event.city || "",
    when: {
      datetime: event.startAt,
      label: formatEventWhen(event.startAt, event.endAt, { withEnd: true, withYear: true }),
    },
    creator: {
      name: creatorName,
      initials: initials(creatorName),
      school: event.creator?.schoolName || "Organisateur",
      description: event.creator?.description || "",
    },
    description: event.description,
    places: formatRemainingPlaces(event.remainingPlaces),
    availability: event.availability || "unlimited",
    practicalSummary: [
      unlimited ? "Capacité illimitée" : pluralize(event.capacity, "place"),
      event.schoolRestricted ? "Accès réservé à une école" : "Ouvert à toutes les écoles",
    ].join(" · "),
    price: formatPrice(event.priceCents, { isPaid: event.isPaid, currency: event.currency }),
    registrations: `${pluralize(event.registeredCount, "inscrit")}${unlimited ? " · Capacité illimitée" : ` sur ${pluralize(event.capacity, "place")}`}`,
    /** null : capacite illimitee, pas de barre de remplissage */
    capacity: event.capacity === null ? null : {
      max: event.capacity,
      value: event.registeredCount,
      label: `${event.registeredCount} / ${event.capacity}`,
    },
    registerLabel: event.availability === "full" ? "Complet" : "Je m’inscris",
    // La maquette reserve le QR code DayOut et les quotas de fiabilite aux inscriptions gratuites.
    showFreeEntry: !event.isPaid,
    tags: [...event.categories.map((category) => category.label), event.isPaid ? "Payant" : "Gratuit"],
    coverImageUrl: event.coverImageUrl,
  };
}
