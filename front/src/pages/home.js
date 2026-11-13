import { HomeController } from "../controller/HomeController.js";
import { renderSearchBox } from "../view/components/searchBox/searchBox.js";

/** Lance la carte une fois pages/home.html affiche (route `/`, voir app/appRoutes.js). */
export function mountHome({ signal }) {
  // Avant le controller : SearchBox lit les champs de recherche (header + accueil) a sa construction.
  document.querySelector("#home-search-slot")?.replaceChildren(renderSearchBox({ id: "home-search" }));
  new HomeController().start({ query: new URLSearchParams(location.search).get("q") ?? "", signal });
}
