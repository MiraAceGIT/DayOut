import { EventsController } from "../../controller/event/EventsController.js";
import { EventsView } from "../../view/event/EventsView.js";

/** Liste paginee des events a venir (voir app/appRoutes.js).
 * @param {import("../../app/router.js").PageContext} page */
export function mountEvents({ root, signal }) {
  return new EventsController({ view: new EventsView(root) }).start({ signal });
}
