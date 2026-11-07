import { h } from "../../../shared/dom.js";
import { loginUrl } from "../../../core/routes.js";
import { renderEventCard } from "../eventCard/eventCard.js";

export function renderEventPopup({ event, distanceKm }) {
  const card = renderEventCard(event, { variant: "popup", distanceKm });
  // L'inscription reste dirigee vers la connexion en attendant le parcours protege.
  if (event.availability !== "full") {
    card.querySelector(".event-card__body").append(
      h("a", { class: "button button--primary button--small", href: loginUrl() }, "S'inscrire"),
    );
  }
  return card;
}
