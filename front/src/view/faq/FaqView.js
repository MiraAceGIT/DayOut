import { h } from "../../shared/dom.js";
import { highlight } from "../../shared/highlight.js";

/** @typedef {import("../../model/mappers/faqMapper.js").FaqEntry} FaqEntry */

export class FaqView {
  constructor(root = document) {
    /** @type {HTMLElement | null} */
    this.list = root.querySelector("#faq-list");
    /** @type {HTMLElement | null} */
    this.status = root.querySelector("#faq-status");
    /** @type {HTMLElement | null} */
    this.error = root.querySelector("#faq-error");
    /** @type {HTMLElement | null} */
    this.retry = root.querySelector("#faq-retry");
    /** @type {HTMLElement | null} */
    this.searchField = root.querySelector("#faq-search-field");
    /** @type {HTMLInputElement | null} */
    this.search = root.querySelector("#faq-search");
  }

  get isReady() {
    return Boolean(this.list && this.status && this.error && this.retry && this.searchField && this.search);
  }

  /** @param {{ onRetry: () => void, onQuery: (query: string) => void }} handlers
   * @param {AbortSignal} [signal] */
  bind({ onRetry, onQuery }, signal) {
    this.retry?.addEventListener("click", onRetry, { signal });
    // Filtre a chaque frappe : les questions sont deja chargees, aucun appel reseau.
    this.search?.addEventListener("input", () => onQuery(this.search?.value ?? ""), { signal });
  }

  showLoading() {
    this.list?.setAttribute("aria-busy", "true");
    if (this.status) this.status.textContent = "Chargement des questions…";
    if (this.error) this.error.textContent = "";
    if (this.retry) this.retry.hidden = true;
    if (this.searchField) this.searchField.hidden = true;
  }

  endLoading() {
    this.list?.setAttribute("aria-busy", "false");
  }

  /** @param {FaqEntry[]} entries questions a afficher (deja filtrees)
   * @param {{ query?: string, total?: number }} [search] saisie du filtre et nombre total de questions
   * @param {AbortSignal} [signal] */
  showEntries(entries, { query = "", total = entries.length } = {}, signal) {
    if (!this.list) return;
    /** @type {{ button: HTMLElement, panel: HTMLElement } | null} */
    let opened = null;
    const items = entries.map(entry => {
      const questionId = `faq-question-${entry.id}`;
      const answerId = `faq-answer-${entry.id}`;
      const button = h("button", {
        id: questionId, type: "button", class: "faq-question",
        "aria-expanded": "false", "aria-controls": answerId,
      }, h("span", {}, highlight(entry.question, query)), h("span", { class: "faq-icon", "aria-hidden": "true" }, "+"));
      const panel = h("div", {
        id: answerId, class: "faq-answer", hidden: true, "aria-labelledby": questionId,
      }, highlight(entry.answer, query));
      button.addEventListener("click", () => {
        const wasOpen = opened?.button === button;
        if (opened) {
          opened.button.setAttribute("aria-expanded", "false");
          opened.panel.hidden = true;
        }
        opened = wasOpen ? null : { button, panel };
        button.setAttribute("aria-expanded", String(!wasOpen));
        panel.hidden = wasOpen;
      }, { signal });
      return h("section", { class: "faq-item" }, h("h2", {}, button), panel);
    });
    this.list.replaceChildren(...items);
    if (this.searchField) this.searchField.hidden = total === 0;
    if (this.status) this.status.textContent = FaqView.statusText(entries.length, total, query);
  }

  /** @param {number} shown @param {number} total @param {string} query */
  static statusText(shown, total, query) {
    if (total === 0) return "Aucune question disponible pour le moment.";
    const keyword = query.trim();
    if (!keyword) return "";
    if (shown === 0) return `Aucune question ne correspond à « ${keyword} ».`;
    return `${shown} question${shown > 1 ? "s" : ""} sur ${total} correspond${shown > 1 ? "ent" : ""} à « ${keyword} ».`;
  }

  /** @param {string} message */
  showError(message) {
    if (this.status) this.status.textContent = "";
    if (this.error) this.error.textContent = message;
    if (this.retry) this.retry.hidden = false;
  }
}
