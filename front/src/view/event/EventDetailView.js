import { h } from "../../shared/dom.js";
import { setCover } from "../components/cover/cover.js";
import { presentEventDetail } from "./eventDetailPresenter.js";

/** Elements cherches dans `root`, le <main> ou le routeur a pose la page ; garantis par `isReady` (ceux de l'etat)
 * et par pages/event.html (ceux du detail). Le titre est celui du document. */
export class EventDetailView {
  /** @param {HTMLElement} [root] */
  constructor(root = /** @type {HTMLElement} */ (document.getElementById("app-root"))) {
    this.root = root;
    this.main = root;
    this.detail = /** @type {HTMLElement} */ (root.querySelector("#event-detail"));
    this.state = /** @type {HTMLElement} */ (root.querySelector("#event-state"));
    this.stateTitle = /** @type {HTMLElement} */ (root.querySelector("#event-state-title"));
    this.code = /** @type {HTMLElement} */ (root.querySelector("#event-state-code"));
    this.status = /** @type {HTMLElement} */ (root.querySelector("#event-status"));
    this.error = /** @type {HTMLElement} */ (root.querySelector("#event-error"));
    this.retry = /** @type {HTMLButtonElement} */ (root.querySelector("#event-retry"));
  }

  /** Element du detail, present dans pages/event.html. @param {string} selector @returns {HTMLElement} */
  el(selector) {
    return /** @type {HTMLElement} */ (this.root.querySelector(selector));
  }

  get isReady() {
    return Boolean(this.main && this.detail && this.state && this.stateTitle && this.code && this.status && this.error && this.retry);
  }

  /** @param {{ onRetry: () => void }} handlers @param {AbortSignal} [signal] retire l'ecouteur au depart de la page */
  bind({ onRetry }, signal) {
    this.retry.addEventListener("click", onRetry, { signal });
  }

  showLoading() {
    this.main.setAttribute("aria-busy", "true");
    this.detail.hidden = true;
    this.state.hidden = false;
    this.code.hidden = true;
    this.stateTitle.textContent = "Détail de l’événement";
    this.status.textContent = "Chargement de l’événement…";
    this.error.textContent = "";
    this.retry.hidden = true;
    this.retry.disabled = true;
    document.title = "Événement — DayOut";
  }

  endLoading() {
    this.main.setAttribute("aria-busy", "false");
    this.retry.disabled = false;
  }

  showNotFound() {
    this.detail.hidden = true;
    this.state.hidden = false;
    this.code.hidden = false;
    this.stateTitle.textContent = "Événement introuvable";
    this.status.textContent = "Cet événement n’existe pas ou n’est plus disponible.";
    this.error.textContent = "";
    this.retry.hidden = true;
    document.title = "404 — Événement introuvable — DayOut";
    this.stateTitle.focus();
  }

  showError(message) {
    this.detail.hidden = true;
    this.state.hidden = false;
    this.code.hidden = true;
    this.stateTitle.textContent = "Impossible de charger l’événement";
    this.status.textContent = "";
    this.error.textContent = message;
    this.retry.hidden = false;
    document.title = "Chargement impossible — DayOut";
    this.stateTitle.focus();
  }

  /** @param {import("./eventDetailPresenter.js").Event} event */
  showEvent(event) {
    const view = presentEventDetail(event);
    const text = (selector, value) => {
      for (const el of this.root.querySelectorAll(selector)) el.textContent = value;
    };
    text("#event-title", view.title);
    text("#event-venue", view.place);
    this.el("#event-date").replaceChildren(
      h("time", { datetime: view.when.datetime }, view.when.label),
      h("span", { class: "event-date__timezone event-note" }, "Heure de Paris"),
    );
    text("#event-address, #event-map-address", view.address);
    text("#event-map-name", view.place);
    text("#event-map-city", view.city);
    text("[data-event-creator-name]", view.creator.name);
    text("#event-creator-school", view.creator.school);
    text("#event-creator-description", view.creator.description);
    text("#event-creator-avatar", view.creator.initials);
    text("#event-description", view.description);
    text("#event-places", view.places);
    this.el("#event-places").setAttribute("data-availability", view.availability);
    text("#event-practical-summary", view.practicalSummary);
    text("#registration-title", view.price);
    text("#event-registration-count", view.registrations);
    const progress = /** @type {HTMLProgressElement} */ (this.el("#event-capacity"));
    progress.hidden = view.capacity === null;
    if (view.capacity !== null) {
      progress.max = view.capacity.max;
      progress.value = view.capacity.value;
      progress.textContent = view.capacity.label;
    }
    text("#event-register", view.registerLabel);
    this.el("#event-free-entry").toggleAttribute("hidden", !view.showFreeEntry);
    this.el("#event-free-reliability").toggleAttribute("hidden", !view.showFreeEntry);
    this.el("#event-tags").replaceChildren(...view.tags.map((label) => h("span", {}, label)));
    setCover(/** @type {HTMLImageElement} */ (this.el("#event-cover")), view.coverImageUrl);
    this.state.hidden = true;
    this.detail.hidden = false;
    this.status.textContent = "";
    this.error.textContent = "";
    document.title = view.documentTitle;
  }
}
