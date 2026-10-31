import { boot } from "../app/boot.js";
import { HomeController } from "../controller/HomeController.js";

boot({ page: "home" });

new HomeController().start({ query: new URLSearchParams(location.search).get("q") ?? "" });
