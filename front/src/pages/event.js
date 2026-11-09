import { EventDetailController } from "../controller/EventDetailController.js";

/** Lance le detail une fois pages/event.html affiche (route `/event?id=…`, voir app/appRoutes.js). */
export function mountEvent() {
  const ids = new URLSearchParams(location.search).getAll("id");
  new EventDetailController().start(ids.length === 1 ? ids[0] : null);
}
