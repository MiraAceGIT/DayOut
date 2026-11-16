import { h } from "../../shared/dom.js";

export class FaqView {
  constructor() {
    this.list = document.getElementById("faq-list");
    this.status = document.getElementById("faq-status");
    this.error = document.getElementById("faq-error");
    this.retry = document.getElementById("faq-retry");
  }

  get isReady() {
    return !!(this.list && this.status && this.error && this.retry);
  }

  /** @param {() => void} onRetry
   * @param {AbortSignal} [signal] */
  bind(onRetry, signal) {
    this.retry?.addEventListener("click", onRetry, { signal });
  }

  showLoading() {
    this.list?.setAttribute("aria-busy", "true");
    if (this.status) this.status.textContent = "Chargement des questions…";
    if (this.error) this.error.textContent = "";
    if (this.retry) this.retry.hidden = true;
  }

  endLoading() {
    this.list?.setAttribute("aria-busy", "false");
  }

  /** @param {import("../../model/services/faqService.js").FaqEntry[]} entries
   * @param {AbortSignal} [signal] */
  showEntries(entries, signal) {
    if (!this.list) return;
    /** @type {{ button: HTMLElement, panel: HTMLElement } | null} */
    let opened = null;
    const items = entries.map(entry => {
      const questionId = `faq-question-${entry.id}`;
      const answerId = `faq-answer-${entry.id}`;
      const button = h("button", {
        id: questionId, type: "button", class: "faq-question",
        "aria-expanded": "false", "aria-controls": answerId,
      }, h("span", {}, entry.question), h("span", { class: "faq-icon", "aria-hidden": "true" }, "+"));
      const panel = h("div", {
        id: answerId, class: "faq-answer", hidden: true, "aria-labelledby": questionId,
      }, entry.answer);
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
    if (this.status) this.status.textContent = entries.length ? "" : "Aucune question disponible pour le moment.";
  }

  /** @param {string} message */
  showError(message) {
    if (this.status) this.status.textContent = "";
    if (this.error) this.error.textContent = message;
    if (this.retry) this.retry.hidden = false;
  }
}
