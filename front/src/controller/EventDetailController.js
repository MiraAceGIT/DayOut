import { isApiError } from "../core/errors.js";
import { fetchEvent } from "../model/services/eventService.js";
import { parseEventId } from "../model/domain/eventId.js";
import { EventDetailView } from "../view/event/EventDetailView.js";
import { PageLoader } from "./PageLoader.js";

export class EventDetailController {
  /** @param {{ view?: Pick<EventDetailView, "isReady" | "bind" | "showLoading" | "endLoading" | "showEvent" | "showNotFound" | "showError">,
   * loadEvent?: typeof fetchEvent }} [options] */
  constructor({ view = new EventDetailView(), loadEvent = fetchEvent } = {}) {
    this.view = view;
    this.loadEvent = loadEvent;
    this.loader = new PageLoader(view);
    this.id = null;
    this.started = false;
  }

  /** @param {{ id?: string|null, signal?: AbortSignal }} [options] `id` brut de l'URL ; `signal` : depart de la page */
  async start({ id = null, signal } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.loader = new PageLoader(this.view, { signal });
    this.id = parseEventId(id);
    this.view.bind({ onRetry: () => this.load() }, signal);
    if (this.id === null) {
      this.view.showNotFound();
      this.view.endLoading();
      return;
    }
    await this.load();
  }

  async load() {
    const id = this.id;
    if (id === null) return;
    await this.loader.run((signal) => this.loadEvent(id, { signal }), {
      onSuccess: (event) => this.view.showEvent(event),
      // Inexistant ou non publie : page 404 plutot qu'un message d'erreur.
      onError: (error) => {
        if (!isApiError(error) || error.status !== 404) return false;
        this.view.showNotFound();
        return true;
      },
    });
  }
}
