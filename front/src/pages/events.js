import { PATHS } from "../core/routes.js";
import { EventsController } from "../controller/EventsController.js";

let controller = null;

export const events = {
  id: "events",
  path: PATHS.events,
  title: "Événements",
  template: "screen-events",
  mainClass: "container",
  mount() {
    controller = new EventsController();
    controller.start();
  },
  unmount() {
    controller = null;
  },
};
