import { isApiError, messageFor } from "../core/errors.js";
import { fetchUpcomingEvents } from "../model/services/eventService.js";
import { EventsView } from "../view/EventsView.js";

export class EventsController {
  constructor({ view = new EventsView(), loadEvents = fetchUpcomingEvents, limit = 20 } = {}) {
    this.view = view;
    this.loadEvents = loadEvents;
    this.limit = limit;
    this.abort = new AbortController();
    this.stopped = false;
  }

  async start() {
    if (!this.view.isReady) return;
    this.view.showLoading();

    try {
      const events = await this.loadEvents({ limit: this.limit, signal: this.abort.signal });
      if (this.stopped) return;
      this.view.showEvents(events);
    } catch (err) {
      // Ecran quitte : ni l'annulation ni une reponse tardive ne s'affichent.
      if (this.stopped || (isApiError(err) && err.type === "abort")) return;
      // ApiError = echec attendu ; toute autre erreur est un bug.
      if (!isApiError(err)) console.error(err);
      this.view.showError(messageFor(err));
    } finally {
      if (!this.stopped) this.view.endLoading();
    }
  }

  stop() {
    this.stopped = true;
    this.abort.abort();
  }
}
