import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { icon } from "../../components/icon/icon.js";
import { renderLogo } from "../../components/logo/logo.js";
import { renderSearchBox } from "../../components/searchBox/searchBox.js";

// Routes de app/appRoutes.js ; les pages pas encore faites affichent "Page introuvable" (pages/not-found.html).
const NAV_ITEMS = [
  { label: "Carte",          path: "/" },
  { label: "Pour toi",       path: "/for-you" },
  { label: "Mes événements", path: "/my-events" },
  { label: "Planning",       path: "/planning" },
];

const MENU_ID = "site-menu";

/** Dessine le header une seule fois ; renvoie la fonction qui marque le lien de la page affichee. */
export function renderHeader() {
  const host = document.querySelector("header[data-layout]");
  if (!host) return () => {};

  const links = NAV_ITEMS.map((item) => h("a", { class: "site-nav__link", href: routeUrl(item.path) }, item.label));
  // Mobile : le burger ouvre / ferme le menu (recherche + liens) ; un clic sur un lien le referme.
  const burger = h("button", {
    type: "button",
    class: "site-header__burger",
    "aria-label": "Menu",
    "aria-controls": MENU_ID,
    "aria-expanded": "false",
    onclick: () => burger.setAttribute("aria-expanded", String(burger.getAttribute("aria-expanded") === "false")),
  }, icon("menu", { size: 24 }));
  const menu = h("div", {
    class: "site-header__menu",
    id: MENU_ID,
    onclick: (e) => {
      if (e.target instanceof Element && e.target.closest("a")) burger.setAttribute("aria-expanded", "false");
    },
  },
    renderSearchBox(),
    h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
  );
  // TODO : ouvrir la connexion quand la page existera (pour l'instant, le bouton ne fait rien).
  const login = h("button", { type: "button", class: "button button--on-dark button--small site-header__login" }, "Connexion");

  host.classList.add("site-header");
  host.replaceChildren(
    h("div", { class: "site-header__inner" },
      renderLogo({ className: "site-header__logo" }),
      burger,
      menu,
      login,
    ),
  );

  return (path) => {
    links.forEach((link, i) => {
      if (NAV_ITEMS[i].path === path) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });
  };
}
