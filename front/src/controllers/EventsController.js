import { isApiError, messageFor } from "../core/errors.js";
import { fetchUpcomingEvents, remainingPlaces } from "../models/Event.js";
import { EventsView } from "../views/EventsView.js";

export class EventsController {
  constructor({ view = new EventsView(), limit = 20 } = {}) {
    this.view = view;
    this.limit = limit;
  }

  async start() {
    if (!this.view.isReady) return;
    this.view.showLoading();

    try {
      const events = await fetchUpcomingEvents({ limit: this.limit });
      this.view.showEvents(events.map((event) => ({ event, remaining: remainingPlaces(event) })));
    } catch (err) {
      // Une ApiError est un echec attendu (reseau, HTTP...) ; toute autre erreur est un bug, a garder en console.
      if (!isApiError(err)) console.error(err);
      this.view.showError(messageFor(err));
    } finally {
      this.view.endLoading();
    }
  }
}
