import { h } from "../../../shared/dom.js";
import { pluralize } from "../../../shared/format.js";
import { renderEventCard } from "../../components/eventCard/eventCard.js";

/** Les suggestions utilisent le meme rendu de carte que la page liste. */
export class SuggestionsList {
  constructor({ listEl, statusEl, errorEl }) {
    this.listEl = listEl;
    this.statusEl = statusEl;
    this.errorEl = errorEl;
  }

  get isReady() {
    return Boolean(this.listEl && this.statusEl && this.errorEl);
  }

  setBusy(busy) {
    this.listEl.setAttribute("aria-busy", String(busy));
  }

  showLoading() {
    this.errorEl.textContent = "";
    this.statusEl.textContent = "Chargement des suggestions…";
    this.setBusy(true);
  }

  render(events) {
    this.errorEl.textContent = "";
    this.listEl.replaceChildren(...events.map(event => h("li", {}, renderEventCard(event))));
    this.statusEl.textContent = events.length
      ? `${pluralize(events.length, "événement")} à découvrir.`
      : "Aucune suggestion disponible pour le moment.";
  }

  showError(message) {
    this.listEl.replaceChildren();
    this.statusEl.textContent = "";
    this.errorEl.textContent = message;
  }
}
