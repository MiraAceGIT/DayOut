import { h } from "../../../shared/dom.js";
import { PATHS, loginUrl, routeHref } from "../../../core/routes.js";
import { renderLogo } from "../../components/logo/logo.js";
import { renderSearchBox } from "./searchBox.js";

// TODO : "Pour toi", "Mes evenements", "Planning" quand ces pages existeront.
export const NAV_ITEMS = [
  { id: "home",   label: "Carte",      path: PATHS.home },
  { id: "events", label: "Événements", path: PATHS.events },
];

/** Dessine le header une fois ; renvoie `{ setCurrent(id) }`, ou null sans `header[data-layout]`. */
export function renderHeader({ current } = {}) {
  const host = document.querySelector("header[data-layout]");
  if (!host) return null;

  const links = NAV_ITEMS.map((item) =>
    h("a", { class: "site-nav__link", href: routeHref(item.path) }, item.label)
  );
  const login = h("a", { class: "button button--on-dark button--small site-header__login", href: loginUrl() }, "Connexion");

  host.classList.add("site-header");
  host.replaceChildren(
    h("div", { class: "site-header__inner" },
      renderLogo({ className: "site-header__logo" }),
      renderSearchBox(),
      h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
      login,
    ),
  );

  function setCurrent(id) {
    links.forEach((link, i) => {
      if (NAV_ITEMS[i].id === id) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });
    // Le header n'est pas redessine : `next` doit suivre l'ecran affiche.
    login.setAttribute("href", loginUrl());
  }

  setCurrent(current);
  return { setCurrent };
}
