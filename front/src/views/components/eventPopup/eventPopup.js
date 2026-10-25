import { h } from "../../../shared/dom.js";
import { frontUrl } from "../../../core/config.js";
import { loginUrl } from "../../../core/routes.js";
import { formatEventWhen } from "../../../shared/format.js";
import { colorCategoryOf } from "../category/category.js";
import { distanceText, joinMeta, placeOf } from "../eventMeta/eventMeta.js";
import { renderCategoryPill, renderPlacesPill } from "../pill/pill.js";

/**
 * Contenu de la bulle du marqueur selectionne. `item` = { event, distanceKm }.
 * TODO : "Voir l'evenement" vers la future page detail ; "S'inscrire" vers l'inscription une fois connecte.
 */
export function renderEventPopup({ event, distanceKm }) {
  const when = joinMeta(formatEventWhen(event.startAt, event.endAt, { withEnd: true }), distanceText(distanceKm));
  const place = placeOf(event);

  return h("div", { class: "event-popup" },
    h("div", { class: "pill-row" },
      renderCategoryPill(colorCategoryOf(event)),
      renderPlacesPill(event),
    ),
    h("h3", { class: "event-popup__title" }, event.title),
    when ? h("p", { class: "event-popup__meta" }, when) : null,
    place ? h("p", { class: "event-popup__meta" }, place) : null,
    h("div", { class: "event-popup__actions" },
      h("a", { class: "event-popup__link", href: frontUrl("pages/events.html").href },
        "Voir l'événement ", h("span", { "aria-hidden": "true" }, "→")),
      // Inscription reservee aux comptes : un visiteur passe d'abord par la connexion.
      event.availability === "full"
        ? null
        : h("a", { class: "button button--primary button--small", href: loginUrl() }, "S'inscrire"),
    ),
  );
}
