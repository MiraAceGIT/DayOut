import { HomeController } from "../controller/HomeController.js";
import { HomeView } from "../view/home/HomeView.js";
import { MapView } from "../view/home/map/MapView.js";

/** @param {import("../app/router.js").PageContext} page */
export function mountHome({ root, signal, params, setParam }) {
  return new HomeController({ view: new HomeView(root), map: new MapView(root) })
    .start({ query: params.get("q") ?? "", signal, setParam });
}
