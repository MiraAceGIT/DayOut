import { h } from "../../../shared/dom.js";
import { formatEventWhen } from "../../../shared/format.js";
import { categoryClass, colorCategoryOf } from "../category/category.js";
import { distanceText, joinMeta } from "../eventMeta/eventMeta.js";
import { renderCategoryPill, renderPlacesPill, renderPricePill } from "../pill/pill.js";

/** Le titre est un bouton etire sur toute la carte ; `aria-current="true"` = selection. */
export function renderEventRow({ event, distanceKm }, { selected = false, onSelect } = {}) {
  const category = colorCategoryOf(event);
  const details = joinMeta(
    formatEventWhen(event.startAt),
    event.venueName || event.city,
    distanceText(distanceKm, { unknown: "position inconnue" }),
  );

  return h("li", { class: ["event-row", categoryClass(category)] },
    h("span", { class: "event-row__square", "aria-hidden": "true" }),
    h("div", { class: "event-row__body" },
      category ? h("div", { class: "pill-row" }, renderCategoryPill(category)) : null,
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
        renderPlacesPill(event),
        renderPricePill(event),
      ),
    ),
  );
}
