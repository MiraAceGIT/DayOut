import { messageFor } from "../core/errors.js";
import { searchFaq } from "../model/domain/faqSearch.js";
import { fetchFaq } from "../model/services/faqService.js";
import { FaqView } from "../view/faq/FaqView.js";

/** @typedef {import("../model/mappers/faqMapper.js").FaqEntry} FaqEntry */

export class FaqController {
  /** @param {{ view?: Pick<FaqView, "isReady" | "bind" | "showLoading" | "endLoading" | "showEntries" | "showError">,
   * loadFaq?: typeof fetchFaq, signal?: AbortSignal }} [options] */
  constructor({ view = new FaqView(), loadFaq = fetchFaq, signal } = {}) {
    this.view = view;
    this.loadFaq = loadFaq;
    this.signal = signal;
    this.busy = false;
    /** @type {FaqEntry[]} */
    this.entries = [];
    this.query = "";
  }

  async start() {
    if (!this.view.isReady || this.signal?.aborted) return;
    this.view.bind({ onRetry: () => this.load(), onQuery: (query) => this.filter(query) }, this.signal);
    await this.load();
  }

  async load() {
    if (this.busy || this.signal?.aborted) return;
    this.busy = true;
    this.view.showLoading();
    try {
      const entries = await this.loadFaq({ signal: this.signal });
      if (this.signal?.aborted) return;
      this.entries = entries;
      this.render();
    } catch (error) {
      if (!this.signal?.aborted) this.view.showError(messageFor(error));
    } finally {
      this.busy = false;
      if (!this.signal?.aborted) this.view.endLoading();
    }
  }

  /** @param {string} query */
  filter(query) {
    this.query = query;
    if (!this.busy) this.render();
  }

  render() {
    this.view.showEntries(searchFaq(this.entries, this.query), { query: this.query, total: this.entries.length }, this.signal);
  }
}
