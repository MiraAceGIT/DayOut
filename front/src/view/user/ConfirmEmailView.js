import { routeUrl } from "../../core/routes.js";
import { CONFIRM_LOADING, confirmOutcome, REGISTER, REGISTER_AGAIN } from "./confirmEmailMessages.js";

/** @typedef {import("./confirmEmailMessages.js").ConfirmOutcome} ConfirmOutcome */

export class ConfirmEmailView {
  /** @param {ParentNode} [root] */
  constructor(root = document) {
    this.title = /** @type {HTMLElement} */ (root.querySelector("#confirm-email-title"));
    this.status = /** @type {HTMLElement} */ (root.querySelector("#confirm-email-status"));
    this.error = /** @type {HTMLElement} */ (root.querySelector("#confirm-email-error"));
    this.text = /** @type {HTMLElement} */ (root.querySelector("#confirm-email-text"));
    this.retry = /** @type {HTMLButtonElement} */ (root.querySelector("#confirm-email-retry"));
    this.home = /** @type {HTMLAnchorElement} */ (root.querySelector("#confirm-email-home"));
    this.register = /** @type {HTMLAnchorElement} */ (root.querySelector("#confirm-email-register"));
  }

  get isReady() {
    return Boolean(this.title && this.status && this.error && this.text && this.retry && this.home && this.register);
  }

  /** @param {{ onRetry: () => void }} handlers @param {AbortSignal} [signal] */
  bind({ onRetry }, signal) {
    this.home.href = routeUrl("/");
    this.register.href = routeUrl("/register");
    this.retry.addEventListener("click", onRetry, { signal });
  }

  showLoading() {
    this.status.textContent = CONFIRM_LOADING;
    this.error.textContent = "";
    this.retry.hidden = true;
  }

  endLoading() {
    if (this.status.textContent === CONFIRM_LOADING) this.status.textContent = "";
  }

  /** @param {string} message erreur reseau ou serveur : le lien n'a pas ete consomme, on peut reessayer */
  showError(message) {
    this.status.textContent = "";
    this.error.textContent = message;
    this.retry.hidden = false;
  }

  /** @param {ConfirmOutcome} outcome @param {{ creatorPending?: boolean }} [options] */
  showOutcome(outcome, options) {
    const { title, text } = confirmOutcome(outcome, options);
    this.title.textContent = title;
    this.text.textContent = text;
    this.error.textContent = "";
    this.retry.hidden = true;
    // Lien expire : refaire l'inscription remplace le compte non confirme et renvoie un lien.
    this.home.hidden = outcome !== "confirmed" && outcome !== "used";
    this.register.hidden = outcome !== "expired" && outcome !== "invalid";
    this.register.textContent = outcome === "expired" ? REGISTER_AGAIN : REGISTER;
    document.title = `${title} — DayOut`;
    this.title.focus();
  }
}
