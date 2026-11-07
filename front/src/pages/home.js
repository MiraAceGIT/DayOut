import { PATHS } from "../core/routes.js";
import { HomeController } from "../controller/HomeController.js";

let controller = null;

export const home = {
  id: "home",
  path: PATHS.home,
  title: "Que faire autour de toi ?",
  template: "screen-home",
  mainClass: "map-layout",
  bodyClass: "page-map",
  mount() {
    controller = new HomeController();
    controller.start({ query: new URLSearchParams(location.search).get("q") ?? "" });
  },
  unmount() {
    controller?.stop();
    controller = null;
  },
};
