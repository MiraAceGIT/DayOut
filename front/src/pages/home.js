import { HomeController } from "../controller/HomeController.js";
import { renderSearchBox } from "../view/components/searchBox/searchBox.js";

/** Carte (route `/`, voir app/appRoutes.js) ; `?q=` : recherche venue du header ou d'une autre page.
 * @param {{ signal: AbortSignal, params: URLSearchParams }} page */
export function mountHome({ signal, params }) {
  // Avant le controller : SearchBox lit les champs de recherche (header + accueil) a sa construction.
  document.querySelector("#home-search-slot")?.replaceChildren(renderSearchBox({ id: "home-search" }));
  return new HomeController().start({ query: params.get("q") ?? "", signal });
}
