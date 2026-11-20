import { renderEventRow } from "../../components/eventRow/eventRow.js";

export class ResultsList {
  constructor(listEl) {
    this.listEl = listEl;
  }

  setBusy(busy) {
    this.listEl.setAttribute("aria-busy", String(busy));
  }

  render(items, { selectedId = null, keyword = "", onSelect } = {}) {
    this.listEl.replaceChildren(...items.map((item) => renderEventRow(item, {
      selected: item.event.id === selectedId,
      keyword,
      onSelect,
    })));
  }

  clear() {
    this.listEl.replaceChildren();
  }

  markSelected(id, { scroll = false } = {}) {
    for (const btn of this.listEl.querySelectorAll(".event-card__select")) {
      const selected = btn.dataset.eventId === String(id);
      if (selected) {
        btn.setAttribute("aria-current", "true");
        if (scroll) {
          const reduce = matchMedia("(prefers-reduced-motion: reduce)").matches;
          btn.closest(".event-card")?.scrollIntoView({ block: "nearest", behavior: reduce ? "auto" : "smooth" });
        }
      } else {
        btn.removeAttribute("aria-current");
      }
    }
  }
}
