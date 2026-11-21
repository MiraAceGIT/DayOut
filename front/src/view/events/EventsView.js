import { renderEventCard } from "../components/eventCard/eventCard.js";
import { errorStatus, paginationStatus } from "./eventsMessages.js";

/** Elements cherches dans `root`, le <main> ou le routeur a pose la page. */
export class EventsView {
  /** @param {ParentNode} [root] */
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

  /** @param {{ onLoadMore: () => void }} handlers @param {AbortSignal} [signal] retire l'ecouteur au depart de la page */
  bind({ onLoadMore }, signal) {
    this.moreBtn.addEventListener("click", onLoadMore, { signal });
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
    this.setStatus(paginationStatus(this.count, { hasMore, limitReached }));
  }

  showError(message) {
    // Une erreur sur une page suivante ne doit pas effacer les cartes deja chargees.
    this.setStatus(errorStatus(this.count));
    this.errorEl.textContent = message;
    this.moreBtn.hidden = false;
    this.moreBtn.textContent = "Réessayer";
  }
}
