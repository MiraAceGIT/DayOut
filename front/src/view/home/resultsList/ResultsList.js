import { renderEventRow } from "../../components/eventRow/eventRow.js";

/** Liste des evenements du panneau (contenu accessible principal) et sa selection. */
export class ResultsList {
  constructor(listEl) {
    this.listEl = listEl;
  }

  setBusy(busy) {
    this.listEl.setAttribute("aria-busy", String(busy));
  }

  /** `items` = [{ event, distanceKm }]. */
  render(items, { selectedId = null, onSelect } = {}) {
    this.listEl.replaceChildren(...items.map((item) => renderEventRow(item, {
      selected: item.event.id === selectedId,
      onSelect,
    })));
  }

  clear() {
    this.listEl.replaceChildren();
  }

  /** Synchronise la bordure orange ; `scroll` quand la selection vient de la carte. */
  markSelected(id, { scroll = false } = {}) {
    for (const btn of this.listEl.querySelectorAll(".event-row__select")) {
      const selected = btn.dataset.eventId === String(id);
      if (selected) {
        btn.setAttribute("aria-current", "true");
        if (scroll) {
          const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
          btn.closest(".event-row")?.scrollIntoView({ block: "nearest", behavior: reduce ? "auto" : "smooth" });
        }
      } else {
        btn.removeAttribute("aria-current");
      }
    }
  }
}
