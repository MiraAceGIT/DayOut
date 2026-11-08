import { isApiError, messageFor } from "../core/errors.js";
import { fetchEvent } from "../model/services/eventService.js";
import { parseEventId } from "../model/domain/eventId.js";
import { EventDetailView } from "../view/event/EventDetailView.js";

export class EventDetailController {
  /** @param {{ view?: Pick<EventDetailView, "isReady" | "bind" | "showLoading" | "endLoading" | "showEvent" | "showNotFound" | "showError">,
   * loadEvent?: typeof fetchEvent }} [options] */
  constructor({ view = new EventDetailView(), loadEvent = fetchEvent } = {}) {
    this.view = view;
    this.loadEvent = loadEvent;
    this.id = null;
    this.busy = false;
    this.started = false;
  }

  /** @param {string|null} id */
  async start(id) {
    if (!this.view.isReady || this.started) return;
    this.started = true;
    this.id = parseEventId(id);
    this.view.bind({ onRetry: () => this.load() });
    if (this.id === null) {
      this.view.showNotFound();
      this.view.endLoading();
      return;
    }
    await this.load();
  }

  async load() {
    if (this.busy || this.id === null) return;
    this.busy = true;
    this.view.showLoading();
    try {
      this.view.showEvent(await this.loadEvent(this.id));
    } catch (error) {
      if (isApiError(error) && error.status === 404) this.view.showNotFound();
      else {
        if (!isApiError(error)) console.error(error);
        this.view.showError(messageFor(error));
      }
    } finally {
      this.busy = false;
      this.view.endLoading();
    }
  }
}
