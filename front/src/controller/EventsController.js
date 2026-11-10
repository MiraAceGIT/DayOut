import { isApiError, messageFor } from "../core/errors.js";
import { fetchUpcomingEventPage, MAX_EVENTS_LIMIT, MAX_EVENTS_OFFSET } from "../model/services/eventService.js";
import { EventsView } from "../view/EventsView.js";

export class EventsController {
  /** @param {{ view?: Pick<EventsView, "isReady" | "bind" | "showLoading" | "endLoading" | "appendEvents" | "showPagination" | "showError">,
   * loadEvents?: typeof fetchUpcomingEventPage, limit?: number }} [options] */
  constructor({ view = new EventsView(), loadEvents = fetchUpcomingEventPage, limit = 20 } = {}) {
    this.view = view;
    this.loadEvents = loadEvents;
    this.limit = Math.min(Math.max(1, limit), MAX_EVENTS_LIMIT);
    this.offset = 0;
    this.hasMore = true;
    this.busy = false;
    this.started = false;
    this.from = null;
  }

  async start() {
    if (!this.view.isReady || this.started) return;
    this.started = true;
    // Instant de reference fixe : un evenement qui se termine entre deux clics ne decale pas les pages.
    this.from = new Date().toISOString();
    this.view.bind({ onLoadMore: () => this.loadMore() });
    await this.loadMore();
  }

  async loadMore() {
    if (this.busy || !this.hasMore) return;
    this.busy = true;
    this.view.showLoading();

    try {
      const page = await this.loadEvents({ limit: this.limit, offset: this.offset, from: this.from });
      this.view.appendEvents(page.items);
      this.offset = page.offset + page.items.length;
      const limitReached = page.hasMore && this.offset > MAX_EVENTS_OFFSET;
      this.hasMore = page.hasMore && !limitReached;
      this.view.showPagination({ hasMore: this.hasMore, limitReached });
    } catch (err) {
      // ApiError = echec attendu ; toute autre erreur est un bug.
      if (!isApiError(err)) console.error(err);
      this.view.showError(messageFor(err));
    } finally {
      this.busy = false;
      this.view.endLoading();
    }
  }
}
