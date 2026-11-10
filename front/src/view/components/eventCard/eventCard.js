import { h, isSafeUrl } from "../../../shared/dom.js";
import { frontUrl } from "../../../core/config.js";
import { routeUrl } from "../../../core/routes.js";
import { formatEventWhen } from "../../../shared/format.js";
import { categoryClass, colorCategoryOf } from "../category/category.js";
import { distanceText, joinMeta } from "../eventMeta/eventMeta.js";
import { renderCategoryPill, renderPlacesPill, renderPricePill } from "../pill/pill.js";

/** Meme composant pour la grille, les resultats de recherche et les bulles de carte.
 * @param event
 * @param {{ variant?: "grid"|"compact"|"popup", distanceKm?: number|null, selected?: boolean, onSelect?: (id: number) => void }} [options]
 */
export function renderEventCard(event, { variant = "grid", distanceKm = null, selected = false, onSelect } = {}) {
  const compact = variant === "compact";
  const popup = variant === "popup";
  const detailUrl = new URL(routeUrl("/event"));
  detailUrl.searchParams.set("id", String(event.id));
  const fallback = frontUrl("assets/img/event-cover-placeholder.svg").href;
  const image = h("img", {
    class: "event-card__image",
    src: event.coverImageUrl && isSafeUrl(event.coverImageUrl) ? event.coverImageUrl : fallback,
    alt: "", width: 640, height: 360, loading: "lazy", decoding: "async",
  });
  image.addEventListener("error", () => { image.src = fallback; }, { once: true });

  return h("article", { class: ["event-card", `event-card--${variant}`, categoryClass(colorCategoryOf(event))] },
    image,
    h("div", { class: "event-card__body" },
      event.categories.length
        ? h("div", { class: "pill-row" }, event.categories.map(renderCategoryPill))
        : null,
      h(popup ? "h3" : "h2", { class: "event-card__title" },
        compact
          ? h("button", {
            type: "button", class: "event-card__select", dataset: { eventId: event.id },
            "aria-current": selected ? "true" : null, onClick: () => onSelect?.(event.id),
          }, event.title)
          : h("a", { class: "event-card__link", href: detailUrl.href }, event.title),
      ),
      event.startAt ? h("p", { class: "event-card__meta" },
        h("time", { datetime: event.startAt, title: "Heure de Paris" }, formatEventWhen(event.startAt, event.endAt, { withEnd: !compact }))) : null,
      event.venueName || event.city
        ? h("p", { class: "event-card__meta" }, joinMeta(event.venueName, event.city)) : null,
      compact || distanceKm !== null
        ? h("p", { class: "event-card__meta" }, distanceText(distanceKm, { unknown: "Position inconnue" })) : null,
      !compact && !popup && event.description ? h("p", { class: "event-card__desc" }, event.description) : null,
      h("div", { class: "pill-row event-card__status" },
        renderPricePill(event),
        renderPlacesPill(event, { showPlenty: true }),
      ),
      compact ? h("a", { class: "event-card__details", href: detailUrl.href },
        "Voir l’événement", h("span", { class: "visually-hidden" }, ` : ${event.title}`)) : null,
    ),
  );
}
