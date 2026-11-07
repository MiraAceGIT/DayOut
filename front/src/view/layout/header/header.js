import { h } from "../../../shared/dom.js";
import { PATHS, loginUrl, routeHref } from "../../../core/routes.js";
import { icon } from "../../components/icon/icon.js";
import { renderLogo } from "../../components/logo/logo.js";
import { renderSearchBox } from "./searchBox.js";

export const NAV_ITEMS = [
  { id: "home",     label: "Carte",        path: PATHS.home },
  { id: "events",   label: "Événements",   path: PATHS.events },
  { id: "planning", label: "Mon planning", path: PATHS.planning },
];

const MENU_ID = "site-menu";
// Meme seuil que le @media de header.css (les variables CSS ne sont pas lisibles dans une media query).
const MOBILE_QUERY = "(max-width: 900px)";

/** Bouton burger (mobile) : ouvre le panneau du menu, qui se ferme sur Echap, lien, clic exterieur ou ecran large. */
function bindBurger(burger, menu) {
  const isOpen = () => burger.getAttribute("aria-expanded") === "true";
  const setOpen = (open, { focusButton = false } = {}) => {
    burger.setAttribute("aria-expanded", String(open));
    burger.setAttribute("aria-label", open ? "Fermer le menu" : "Ouvrir le menu");
    burger.replaceChildren(icon(open ? "x" : "menu", { size: 24 }));
    if (focusButton) burger.focus();
  };
  const close = (options) => {
    if (isOpen()) setOpen(false, options);
  };

  burger.addEventListener("click", () => setOpen(!isOpen()));
  menu.addEventListener("click", (e) => {
    if (e.target instanceof Element && e.target.closest("a")) close();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") close({ focusButton: true });
  });
  // composedPath et non contains : le clic sur le burger remplace son icone avant d'arriver ici.
  document.addEventListener("click", (e) => {
    const path = e.composedPath();
    if (!path.includes(burger) && !path.includes(menu)) close();
  });
  // Tabulation au-dela du panneau : il ne reste pas ouvert par-dessus le contenu.
  menu.addEventListener("focusout", (e) => {
    const next = e.relatedTarget;
    if (next instanceof Node && !menu.contains(next) && next !== burger) close();
  });
  const mobile = window.matchMedia(MOBILE_QUERY);
  mobile.addEventListener("change", () => {
    if (!mobile.matches) close();
  });
  return close;
}

/** Dessine le header une fois ; renvoie `{ setCurrent(id) }`, ou null sans `header[data-layout]`. */
export function renderHeader({ current } = {}) {
  const host = document.querySelector("header[data-layout]");
  if (!host) return null;

  const links = NAV_ITEMS.map((item) =>
    h("a", { class: "site-nav__link", href: routeHref(item.path) }, item.label)
  );
  const login = h("a", { class: "button button--on-dark button--small site-header__login", href: loginUrl() }, "Connexion");
  const menu = h("div", { class: "site-header__menu", id: MENU_ID },
    h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
    login,
  );
  const burger = h("button", {
    type: "button",
    class: "site-header__burger",
    "aria-expanded": "false",
    "aria-controls": MENU_ID,
    "aria-label": "Ouvrir le menu",
  }, icon("menu", { size: 24 }));

  host.classList.add("site-header");
  host.replaceChildren(
    h("div", { class: "site-header__inner" },
      renderLogo({ className: "site-header__logo" }),
      renderSearchBox(),
      burger,
      menu,
    ),
  );
  const closeMenu = bindBurger(burger, menu);

  function setCurrent(id) {
    links.forEach((link, i) => {
      if (NAV_ITEMS[i].id === id) link.setAttribute("aria-current", "page");
      else link.removeAttribute("aria-current");
    });
    // Le header n'est pas redessine : `next` doit suivre l'ecran affiche.
    login.setAttribute("href", loginUrl());
    closeMenu();
  }

  setCurrent(current);
  return { setCurrent };
}
