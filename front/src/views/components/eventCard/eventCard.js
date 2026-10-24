import { h } from "../../../lib/dom.js";
import { formatDate, formatPrice } from "../../../lib/format.js";

/** `event` : objet normalise par `toEvent()` (models/Event.js). */
export function renderEventCard(event) {
  const when = formatDate(event.startAt);

  return h("article", { class: "event-card" },
    h("h2", { class: "event-card__title" }, event.title),
    h("ul", { class: "event-card__meta" },
      when ? h("li", {}, h("time", { datetime: event.startAt }, when)) : null,
      event.city ? h("li", {}, event.city) : null,
      h("li", { class: "event-card__price" },
        formatPrice(event.priceCents, { isPaid: event.isPaid, currency: event.currency })),
    ),
    event.description ? h("p", { class: "event-card__desc" }, event.description) : null,
    h("p", { class: "event-card__capacity" },
      `${event.registeredCount} / ${event.capacity ?? "∞"} inscrits`),
  );
}
