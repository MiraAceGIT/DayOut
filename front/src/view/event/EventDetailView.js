import { h, isSafeUrl } from "../../shared/dom.js";
import { formatEventWhen, formatPrice, formatRemainingPlaces, pluralize } from "../../shared/format.js";
import { frontUrl } from "../../core/config.js";

export class EventDetailView {
  constructor(root = document) {
    this.root = root;
    this.main = root.querySelector("#app-root");
    this.detail = /** @type {HTMLElement|null} */ (root.querySelector("#event-detail"));
    this.state = /** @type {HTMLElement|null} */ (root.querySelector("#event-state"));
    this.stateTitle = /** @type {HTMLElement|null} */ (root.querySelector("#event-state-title"));
    this.code = /** @type {HTMLElement|null} */ (root.querySelector("#event-state-code"));
    this.status = root.querySelector("#event-status");
    this.error = root.querySelector("#event-error");
    this.retry = /** @type {HTMLButtonElement|null} */ (root.querySelector("#event-retry"));
  }

  get isReady() {
    return Boolean(this.main && this.detail && this.state && this.stateTitle && this.code && this.status && this.error && this.retry);
  }

  bind({ onRetry }) {
    this.retry.addEventListener("click", onRetry);
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
    this.root.title = "Événement — DayOut";
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
    this.root.title = "404 — Événement introuvable — DayOut";
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
    this.root.title = "Chargement impossible — DayOut";
    this.stateTitle.focus();
  }

  /** @param {Awaited<ReturnType<import("../../model/services/eventService.js").fetchEvent>>} event */
  showEvent(event) {
    const text = (selector, value) => {
      for (const el of this.root.querySelectorAll(selector)) el.textContent = value;
    };
    const address = [event.addressLine, [event.postalCode, event.city].filter(Boolean).join(" ")].filter(Boolean).join(", ");
    const place = event.venueName || event.city || "Lieu à préciser";
    const name = event.creator.displayName;
    text("#event-title", event.title);
    text("#event-venue", place);
    const date = this.root.querySelector("#event-date");
    date.replaceChildren(
      h("time", { datetime: event.startAt }, formatEventWhen(event.startAt, event.endAt, { withEnd: true, withYear: true })),
      h("span", { class: "event-date__timezone event-note" }, "Heure de Paris"),
    );
    text("#event-address, #event-map-address", address || "Adresse à préciser");
    text("#event-map-name", place);
    text("#event-map-city", event.city || "");
    text("[data-event-creator-name]", name);
    text("#event-creator-school", event.creator.schoolName || "Organisateur");
    text("#event-creator-description", event.creator.description || "");
    text("#event-creator-avatar", name.trim().split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join("").toUpperCase());
    text("#event-description", event.description);
    text("#event-places", formatRemainingPlaces(event.remainingPlaces));
    this.root.querySelector("#event-places").setAttribute("data-availability", event.availability || "unlimited");
    text("#event-practical-summary", [event.capacity === null ? "Capacité illimitée" : pluralize(event.capacity, "place"),
      event.schoolRestricted ? "Accès réservé à une école" : "Ouvert à toutes les écoles"].join(" · "));
    text("#registration-title", formatPrice(event.priceCents, { isPaid: event.isPaid, currency: event.currency }));
    text("#event-registration-count", `${pluralize(event.registeredCount, "inscrit")}${event.capacity === null ? " · Capacité illimitée" : ` sur ${pluralize(event.capacity, "place")}`}`);
    const progress = /** @type {HTMLProgressElement} */ (this.root.querySelector("#event-capacity"));
    progress.hidden = event.capacity === null;
    if (event.capacity !== null) {
      progress.max = event.capacity;
      progress.value = event.registeredCount;
      progress.textContent = `${event.registeredCount} / ${event.capacity}`;
    }
    text("#event-register", event.availability === "full" ? "Complet" : "Je m’inscris");
    // La maquette reserve le QR code DayOut et les quotas de fiabilite aux inscriptions gratuites.
    this.root.querySelector("#event-free-entry").toggleAttribute("hidden", event.isPaid);
    this.root.querySelector("#event-free-reliability").toggleAttribute("hidden", event.isPaid);
    const tags = [...event.categories.map(category => category.label), event.isPaid ? "Payant" : "Gratuit"];
    this.root.querySelector("#event-tags").replaceChildren(...tags.map(label => h("span", {}, label)));
    const cover = /** @type {HTMLImageElement} */ (this.root.querySelector("#event-cover"));
    const fallback = frontUrl("assets/img/event-cover-placeholder.svg").href;
    cover.src = event.coverImageUrl && isSafeUrl(event.coverImageUrl) ? event.coverImageUrl : fallback;
    cover.onerror = () => { cover.onerror = null; cover.src = fallback; };
    this.state.hidden = true;
    this.detail.hidden = false;
    this.status.textContent = "";
    this.error.textContent = "";
    this.root.title = `${event.title} — DayOut`;
  }
}
