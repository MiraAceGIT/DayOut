import { h } from "../../../shared/dom.js";
import { formatEventWhen } from "../../../shared/format.js";
import { categoryClass, colorCategoryOf } from "../category/category.js";
import { joinMeta } from "../eventMeta/eventMeta.js";
import { renderCategoryPill, renderPlacesPill, renderPricePill } from "../pill/pill.js";

export function renderEventCard(event) {
  const details = joinMeta(formatEventWhen(event.startAt, event.endAt, { withEnd: true }), event.venueName, event.city);

  return h("article", { class: ["event-card", categoryClass(colorCategoryOf(event))] },
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
        renderPlacesPill(event, { showPlenty: true }),
      ),
    ),
  );
}
