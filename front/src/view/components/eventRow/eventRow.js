import { h } from "../../../shared/dom.js";
import { renderEventCard } from "../eventCard/eventCard.js";

/**
 * Adaptateur de liste : conserve la selection de la carte dans le composant commun.
 * @param {import("../../../model/domain/eventSearch.js").SearchItem} item resultat de searchEvents()
 * @param {{ selected?: boolean, keyword?: string, onSelect?: (id: number) => void }} [options]
 */
export function renderEventRow({ event, distanceKm }, { selected = false, keyword = "", onSelect } = {}) {
  return h("li", {}, renderEventCard(event, {
    variant: "compact", distanceKm, selected, keyword, onSelect,
  }));
}
