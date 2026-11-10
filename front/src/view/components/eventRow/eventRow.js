import { h } from "../../../shared/dom.js";
import { renderEventCard } from "../eventCard/eventCard.js";

/** Adaptateur de liste : conserve la selection de la carte dans le composant commun. */
export function renderEventRow({ event, distanceKm }, { selected = false, keyword = "", onSelect } = {}) {
  return h("li", {}, renderEventCard(event, {
    variant: "compact", distanceKm, selected, keyword, onSelect,
  }));
}
