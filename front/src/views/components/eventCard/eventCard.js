import { h } from "../../../lib/dom.js";
import { formatEventWhen } from "../../../lib/format.js";
import { renderCategoryPill, renderPlacesPill, renderPricePill } from "../pill/pill.js";

/** `event` : objet normalise par `toEvent()` ; `remaining` : places restantes (remainingPlaces()). */
export function renderEventCard(event, { remaining = null } = {}) {
  const when = formatEventWhen(event.startAt, event.endAt, { withEnd: true });
  const details = [when, event.venueName, event.city].filter(Boolean).join(" · ");

  return h("article", { class: ["event-card", event.category?.key && `cat--${event.category.key}`] },
    h("div", { class: "event-card__body" },
      event.categories.length
        ? h("div", { class: "pill-row" }, event.categories.map(renderCategoryPill))
        : null,
      h("h2", { class: "event-card__title" }, event.title),
      details
        ? h("p", { class: "event-card__meta" },
          event.startAt ? h("time", { datetime: event.startAt }, details) : details)
        : null,
      event.description ? h("p", { class: "event-card__desc" }, event.description) : null,
      h("div", { class: "pill-row event-card__status" },
        renderPricePill(event),
        renderPlacesPill(remaining, { showPlenty: true }),
      ),
    ),
  );
}
