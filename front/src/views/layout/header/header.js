import { h } from "../../../lib/dom.js";
import { frontUrl } from "../../../core/config.js";
import { loginUrl } from "../../../core/routes.js";
import { renderLogo } from "../../components/logo/logo.js";
import { icon } from "../../components/icon/icon.js";

// TODO : "Pour toi", "Mes evenements", "Planning" quand ces pages existeront.
export const NAV_ITEMS = [
  { id: "home",   label: "Carte",      path: "index.html" },
  { id: "events", label: "Événements", path: "pages/events.html" },
];

/** Id du champ de recherche : la page carte le pilote pour filtrer en direct. */
export const SEARCH_INPUT_ID = "site-search";

/** Formulaire GET vers la carte : sans JS ou depuis une autre page, la recherche arrive en `?q=`. */
function renderSearch() {
  return h("form", {
    class: "site-search",
    role: "search",
    action: frontUrl("index.html").href,
    method: "get",
  },
    h("label", { class: "visually-hidden", for: SEARCH_INPUT_ID }, "Rechercher un événement ou un lieu"),
    icon("search", { className: "site-search__icon" }),
    h("input", {
      class: "site-search__input",
      id: SEARCH_INPUT_ID,
      type: "search",
      name: "q",
      placeholder: "Rechercher un événement, un lieu…",
      autocomplete: "off",
    }),
  );
}

export function renderHeader({ current } = {}) {
  const host = document.querySelector("header[data-layout]");
  if (!host) return;

  const links = NAV_ITEMS.map((item) =>
    h("a", {
      class: "site-nav__link",
      href: frontUrl(item.path).href,
      "aria-current": item.id === current ? "page" : null,
    }, item.label)
  );

  host.classList.add("site-header");
  host.replaceChildren(
    h("div", { class: "site-header__inner" },
      renderLogo({ className: "site-header__logo" }),
      renderSearch(),
      h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
      h("a", { class: "button button--on-dark button--small site-header__login", href: loginUrl() }, "Connexion"),
    ),
  );
}
