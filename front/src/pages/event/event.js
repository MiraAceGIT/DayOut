import { EventDetailController } from "../../controller/event/EventDetailController.js";
import { EventDetailView } from "../../view/event/EventDetailView.js";

/** Detail d'un event (route `/event?id=…`, voir app/appRoutes.js) ; un `id` absent ou repete donne la page 404.
 * @param {import("../../app/router.js").PageContext} page */
export function mountEvent({ root, signal, params }) {
  const ids = params.getAll("id");
  return new EventDetailController({ view: new EventDetailView(root) })
    .start({ id: ids.length === 1 ? ids[0] : null, signal });
}
