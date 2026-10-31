import { boot } from "../app/boot.js";
import { EventsController } from "../controller/EventsController.js";

boot({ page: "events" });

new EventsController().start();
