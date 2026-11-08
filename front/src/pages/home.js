import { HomeController } from "../controller/HomeController.js";

/** Lance la carte une fois pages/home.html affiche (route `/`, voir app/appRoutes.js). */
export function mountHome({ signal }) {
  new HomeController().start({ query: new URLSearchParams(location.search).get("q") ?? "", signal });
}
