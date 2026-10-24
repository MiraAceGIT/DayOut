import { renderEventCard } from "./components/eventCard/eventCard.js";

export class EventsView {
  constructor(root = document) {
    this.listEl   = root.querySelector("#events-list");
    this.errorEl  = root.querySelector("#events-error");
    this.statusEl = root.querySelector("#events-status");
  }

  /** Faux si la page ne contient pas les elements attendus. */
  get isReady() {
    return Boolean(this.listEl && this.errorEl && this.statusEl);
  }

  setStatus(message) {
    this.statusEl.textContent = message;
  }

  showLoading() {
    this.errorEl.textContent = "";
    this.listEl.setAttribute("aria-busy", "true");
    this.setStatus("Chargement des événements…");
  }

  endLoading() {
    this.listEl.setAttribute("aria-busy", "false");
  }

  /** `items` = [{ event, remaining }] prepares par le controleur. */
  showEvents(items) {
    this.listEl.replaceChildren(...items.map(({ event, remaining }) => renderEventCard(event, { remaining })));
    this.setStatus(items.length === 0
      ? "Aucun événement à afficher."
      : `${items.length} événement${items.length > 1 ? "s" : ""} à venir.`);
  }

  showError(message) {
    this.listEl.replaceChildren();
    this.setStatus("");
    this.errorEl.textContent = message;
  }
}
