import { boot } from "../app/boot.js";
import { EventDetailController } from "../controller/EventDetailController.js";

boot({ page: "event" });

const ids = new URLSearchParams(location.search).getAll("id");
new EventDetailController().start(ids.length === 1 ? ids[0] : null);
