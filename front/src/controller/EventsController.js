import { isApiError, messageFor } from "../core/errors.js";
import { fetchUpcomingEvents } from "../model/services/eventService.js";
import { EventsView } from "../view/EventsView.js";

export class EventsController {
  constructor({ view = new EventsView(), loadEvents = fetchUpcomingEvents, limit = 20 } = {}) {
    this.view = view;
    this.loadEvents = loadEvents;
    this.limit = limit;
  }

  async start() {
    if (!this.view.isReady) return;
    this.view.showLoading();

    try {
      this.view.showEvents(await this.loadEvents({ limit: this.limit }));
    } catch (err) {
      // ApiError = echec attendu ; toute autre erreur est un bug.
      if (!isApiError(err)) console.error(err);
      this.view.showError(messageFor(err));
    } finally {
      this.view.endLoading();
    }
  }
}
