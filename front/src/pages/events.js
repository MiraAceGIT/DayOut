import { EventsController } from "../controller/EventsController.js";

/** Lance la liste une fois pages/events.html affiche (route `/events`, voir app/appRoutes.js). */
export function mountEvents() {
  new EventsController().start();
}
