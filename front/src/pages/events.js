import { boot } from "../app/boot.js";
import { EventsController } from "../controllers/EventsController.js";

boot({ page: "events" });

new EventsController().start();
