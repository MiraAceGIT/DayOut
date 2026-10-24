import { h } from "../../../lib/dom.js";
import { frontUrl } from "../../../core/config.js";
import { loginUrl } from "../../../core/routes.js";
import { formatDistance, formatEventWhen } from "../../../lib/format.js";
import { renderCategoryPill, renderPlacesPill } from "../pill/pill.js";

/**
 * Contenu de la bulle du marqueur selectionne. `item` = { event, distanceKm, remaining }.
 * TODO : "Voir l'evenement" vers la future page detail ; "S'inscrire" vers l'inscription une fois connecte.
 */
export function renderEventPopup({ event, distanceKm, remaining }) {
  const when = [
    formatEventWhen(event.startAt, event.endAt, { withEnd: true }),
    distanceKm === null ? "" : formatDistance(distanceKm),
  ].filter(Boolean).join(" · ");
  const place = [event.venueName, event.city].filter(Boolean).join(" · ");

  return h("div", { class: "event-popup" },
    h("div", { class: "pill-row" },
      renderCategoryPill(event.category),
      renderPlacesPill(remaining),
    ),
    h("h3", { class: "event-popup__title" }, event.title),
    when ? h("p", { class: "event-popup__meta" }, when) : null,
    place ? h("p", { class: "event-popup__meta" }, place) : null,
    h("div", { class: "event-popup__actions" },
      h("a", { class: "event-popup__link", href: frontUrl("pages/events.html").href },
        "Voir l'événement ", h("span", { "aria-hidden": "true" }, "→")),
      // Inscription reservee aux comptes : un visiteur passe d'abord par la connexion.
      remaining === 0
        ? null
        : h("a", { class: "button button--primary button--small", href: loginUrl() }, "S'inscrire"),
    ),
  );
}
