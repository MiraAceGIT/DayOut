import { EventDetailController } from "../../controller/event/EventDetailController.js";
import { EventDetailView } from "../../view/event/EventDetailView.js";

/** @param {import("../../app/router.js").PageContext} page */
export function mountEvent({ root, signal, params }) {
  const ids = params.getAll("id");
  return new EventDetailController({ view: new EventDetailView(root) })
    .start({ id: ids.length === 1 ? ids[0] : null, signal });
}
