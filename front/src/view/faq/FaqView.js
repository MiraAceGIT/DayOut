import { h } from "../../shared/dom.js";
import { highlight } from "../../shared/highlight.js";
import { faqStatus } from "./faqMessages.js";

/** @typedef {import("../../model/mappers/faq/faqMapper.js").FaqEntry} FaqEntry */

export class FaqView {
  /** @param {ParentNode} [root] */
  constructor(root = document) {
    this.list = /** @type {HTMLElement} */ (root.querySelector("#faq-list"));
    this.status = /** @type {HTMLElement} */ (root.querySelector("#faq-status"));
    this.error = /** @type {HTMLElement} */ (root.querySelector("#faq-error"));
    this.retry = /** @type {HTMLElement} */ (root.querySelector("#faq-retry"));
    this.searchField = /** @type {HTMLElement} */ (root.querySelector("#faq-search-field"));
    this.search = /** @type {HTMLInputElement} */ (root.querySelector("#faq-search"));
  }

  get isReady() {
    return Boolean(this.list && this.status && this.error && this.retry && this.searchField && this.search);
  }

  /** @param {{ onRetry: () => void, onQuery: (query: string) => void }} handlers @param {AbortSignal} [signal] */
  bind({ onRetry, onQuery }, signal) {
    this.retry.addEventListener("click", onRetry, { signal });
    this.search.addEventListener("input", () => onQuery(this.search.value), { signal });
  }

  showLoading() {
    this.list.setAttribute("aria-busy", "true");
    this.status.textContent = "Chargement des questions…";
    this.error.textContent = "";
    this.retry.hidden = true;
    this.searchField.hidden = true;
  }

  endLoading() {
    this.list.setAttribute("aria-busy", "false");
  }

  /** @param {FaqEntry[]} entries
   * @param {{ query?: string, total?: number }} [search]
   * @param {AbortSignal} [signal] */
  showEntries(entries, { query = "", total = entries.length } = {}, signal) {
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
    this.searchField.hidden = total === 0;
    this.status.textContent = faqStatus(entries.length, total, query);
  }

  /** @param {string} message */
  showError(message) {
    this.status.textContent = "";
    this.error.textContent = message;
    this.retry.hidden = false;
  }
}
