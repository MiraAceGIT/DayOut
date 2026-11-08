import { pluralize } from "../shared/format.js";
import { renderEventCard } from "./components/eventCard/eventCard.js";

export class EventsView {
  constructor(root = document) {
    this.listEl   = root.querySelector("#events-list");
    this.errorEl  = root.querySelector("#events-error");
    this.statusEl = root.querySelector("#events-status");
    this.moreBtn  = /** @type {HTMLButtonElement|null} */ (root.querySelector("#events-more"));
    this.count = 0;
  }

  get isReady() {
    return Boolean(this.listEl && this.errorEl && this.statusEl && this.moreBtn);
  }

  bind({ onLoadMore }) {
    this.moreBtn.addEventListener("click", onLoadMore);
  }

  setStatus(message) {
    this.statusEl.textContent = message;
  }

  showLoading() {
    this.errorEl.textContent = "";
    this.listEl.setAttribute("aria-busy", "true");
    this.moreBtn.disabled = true;
    this.setStatus("Chargement des événements…");
  }

  endLoading() {
    this.listEl.setAttribute("aria-busy", "false");
    this.moreBtn.disabled = false;
  }

  appendEvents(events) {
    this.listEl.append(...events.map((event) => renderEventCard(event)));
    this.count += events.length;
  }

  showPagination({ hasMore, limitReached = false }) {
    this.moreBtn.hidden = !hasMore;
    this.moreBtn.textContent = "Voir plus";
    const count = this.count === 0 ? "Aucun événement à venir." : `${pluralize(this.count, "événement")} à venir affiché${this.count > 1 ? "s" : ""}.`;
    this.setStatus(limitReached
      ? `${count} La limite de consultation est atteinte.`
      : `${count}${!hasMore && this.count > 0 ? " Tous les événements ont été affichés." : ""}`);
  }

  showError(message) {
    // Une erreur sur une page suivante ne doit pas effacer les cartes deja chargees.
    this.setStatus(this.count ? `${pluralize(this.count, "événement")} déjà affiché${this.count > 1 ? "s" : ""}.` : "");
    this.errorEl.textContent = message;
    this.moreBtn.hidden = false;
    this.moreBtn.textContent = "Réessayer";
  }
}
