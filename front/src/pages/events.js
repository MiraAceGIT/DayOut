import { EventsController } from "../controller/EventsController.js";

/** Liste paginee des events a venir (voir app/appRoutes.js).
 * @param {{ signal: AbortSignal }} page */
export function mountEvents({ signal }) {
  return new EventsController().start({ signal });
}
