import { pluralize } from "../shared/format.js";
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

  /** `events` normalises par toEvent(). */
  showEvents(events) {
    this.listEl.replaceChildren(...events.map((event) => renderEventCard(event)));
    this.setStatus(events.length === 0
      ? "Aucun événement à afficher."
      : `${pluralize(events.length, "événement")} à venir.`);
  }

  showError(message) {
    this.listEl.replaceChildren();
    this.setStatus("");
    this.errorEl.textContent = message;
  }
}
