import { h } from "../../../lib/dom.js";
import { formatDistance, formatEventWhen } from "../../../lib/format.js";
import { renderCategoryPill, renderPlacesPill, renderPricePill } from "../pill/pill.js";

/**
 * Carte compacte de la liste de la carte. Le titre est un bouton etire sur toute la carte :
 * il selectionne l'evenement (synchronise avec le marqueur) ; `aria-current="true"` = selection.
 * `item` = { event, distanceKm, remaining }.
 */
export function renderEventRow({ event, distanceKm, remaining }, { selected = false, onSelect } = {}) {
  const details = [
    formatEventWhen(event.startAt),
    event.venueName || event.city,
    distanceKm === null ? "position inconnue" : formatDistance(distanceKm),
  ].filter(Boolean).join(" · ");

  return h("li", { class: ["event-row", event.category?.key && `cat--${event.category.key}`] },
    h("span", { class: "event-row__square", "aria-hidden": "true" }),
    h("div", { class: "event-row__body" },
      event.category ? h("div", { class: "pill-row" }, renderCategoryPill(event.category)) : null,
      h("h2", { class: "event-row__title" },
        h("button", {
          type: "button",
          class: "event-row__select",
          dataset: { eventId: event.id },
          "aria-current": selected ? "true" : null,
          onClick: () => onSelect?.(event.id),
        }, event.title),
      ),
      h("p", { class: "event-row__meta" }, details),
      h("div", { class: "pill-row" },
        renderPlacesPill(remaining),
        renderPricePill(event),
      ),
    ),
  );
}
