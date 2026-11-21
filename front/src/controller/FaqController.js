import { searchFaq } from "../model/domain/faqSearch.js";
import { fetchFaq } from "../model/services/faqService.js";
import { FaqView } from "../view/faq/FaqView.js";
import { PageLoader } from "./PageLoader.js";

/** @typedef {import("../model/mappers/faqMapper.js").FaqEntry} FaqEntry */

export class FaqController {
  /** @param {{ view?: Pick<FaqView, "isReady" | "bind" | "showLoading" | "endLoading" | "showEntries" | "showError">,
   * loadFaq?: typeof fetchFaq }} [options] */
  constructor({ view = new FaqView(), loadFaq = fetchFaq } = {}) {
    this.view = view;
    this.loadFaq = loadFaq;
    this.loader = new PageLoader(view);
    /** @type {AbortSignal | undefined} */
    this.signal = undefined;
    /** @type {FaqEntry[]} */
    this.entries = [];
    this.query = "";
    this.started = false;
  }

  /** @param {{ signal?: AbortSignal }} [options] `signal` : depart de la page */
  async start({ signal } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.signal = signal;
    this.loader = new PageLoader(this.view, { signal });
    this.view.bind({ onRetry: () => this.load(), onQuery: (query) => this.filter(query) }, signal);
    await this.load();
  }

  async load() {
    await this.loader.run((signal) => this.loadFaq({ signal }), {
      onSuccess: (entries) => {
        this.entries = entries;
        this.render();
      },
    });
  }

  /** Filtre local a chaque saisie ; pendant le chargement, la saisie est gardee pour le premier rendu.
   * @param {string} query */
  filter(query) {
    this.query = query;
    if (!this.loader.busy) this.render();
  }

  render() {
    this.view.showEntries(searchFaq(this.entries, this.query), { query: this.query, total: this.entries.length }, this.signal);
  }
}
