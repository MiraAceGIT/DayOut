import { messageFor } from "../core/errors.js";
import { fetchFaq } from "../model/services/faqService.js";
import { FaqView } from "../view/faq/FaqView.js";

export class FaqController {
  /** @param {{ view?: Pick<FaqView, "isReady" | "bind" | "showLoading" | "endLoading" | "showEntries" | "showError">,
   * loadFaq?: typeof fetchFaq, signal?: AbortSignal }} [options] */
  constructor({ view = new FaqView(), loadFaq = fetchFaq, signal } = {}) {
    this.view = view;
    this.loadFaq = loadFaq;
    this.signal = signal;
    this.busy = false;
  }

  async start() {
    if (!this.view.isReady || this.signal?.aborted) return;
    this.view.bind(() => this.load(), this.signal);
    await this.load();
  }

  async load() {
    if (this.busy || this.signal?.aborted) return;
    this.busy = true;
    this.view.showLoading();
    try {
      const entries = await this.loadFaq({ signal: this.signal });
      if (!this.signal?.aborted) this.view.showEntries(entries, this.signal);
    } catch (error) {
      if (!this.signal?.aborted) this.view.showError(messageFor(error));
    } finally {
      this.busy = false;
      if (!this.signal?.aborted) this.view.endLoading();
    }
  }
}
