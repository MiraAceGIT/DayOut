import { MAX_PAGE_OFFSET } from "../../core/apiLimits.js";
import { normalizePageLimit } from "../../model/mappers/pageQueryMapper.js";
import { fetchUpcomingEventPage } from "../../model/services/eventService.js";
import { EventsView } from "../../view/event/EventsView.js";
import { PageLoader } from "../PageLoader.js";

export class EventsController {
  /** @param {{ view?: Pick<EventsView, "isReady" | "bind" | "showLoading" | "endLoading" | "appendEvents" | "showPagination" | "showError">,
   * loadEvents?: typeof fetchUpcomingEventPage, limit?: number }} [options] */
  constructor({ view = new EventsView(), loadEvents = fetchUpcomingEventPage, limit = 20 } = {}) {
    this.view = view;
    this.loadEvents = loadEvents;
    this.loader = new PageLoader(view);
    this.limit = normalizePageLimit(limit);
    this.offset = 0;
    this.hasMore = true;
    this.started = false;
    /** @type {string|undefined} */
    this.from = undefined;
  }

  /** @param {{ signal?: AbortSignal }} [options] `signal` : depart de la page */
  async start({ signal } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.loader = new PageLoader(this.view, { signal });
    // Instant de reference fixe : un evenement qui se termine entre deux clics ne decale pas les pages.
    this.from = new Date().toISOString();
    this.view.bind({ onLoadMore: () => this.loadMore() }, signal);
    await this.loadMore();
  }

  /** Une erreur garde les cartes deja affichees ; le meme offset est redemande au clic suivant. */
  async loadMore() {
    if (!this.hasMore) return;
    await this.loader.run((signal) => this.loadEvents({ limit: this.limit, offset: this.offset, from: this.from, signal }), {
      onSuccess: (page) => {
        this.view.appendEvents(page.items);
        this.offset = page.offset + page.items.length;
        const limitReached = page.hasMore && this.offset > MAX_PAGE_OFFSET;
        this.hasMore = page.hasMore && !limitReached;
        this.view.showPagination({ hasMore: this.hasMore, limitReached });
      },
    });
  }
}
