import { h } from "../../../../shared/dom.js";
import { renderEventCard } from "../eventCard/eventCard.js";

/**
 * @param {import("../../../../model/domain/event/eventSearch.js").SearchItem} item
 * @param {{ selected?: boolean, keyword?: string, onSelect?: (id: number) => void }} [options]
 */
export function renderEventRow({ event, distanceKm }, { selected = false, keyword = "", onSelect } = {}) {
  return h("li", {}, renderEventCard(event, {
    variant: "compact", distanceKm, selected, keyword, onSelect,
  }));
}
