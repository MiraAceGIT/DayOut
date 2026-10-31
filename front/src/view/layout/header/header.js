import { h } from "../../../shared/dom.js";
import { frontUrl } from "../../../core/config.js";
import { loginUrl } from "../../../core/routes.js";
import { renderLogo } from "../../components/logo/logo.js";
import { renderSearchBox } from "./searchBox.js";

// TODO : "Pour toi", "Mes evenements", "Planning" quand ces pages existeront.
export const NAV_ITEMS = [
  { id: "home",   label: "Carte",      path: "index.html" },
  { id: "events", label: "Événements", path: "pages/events.html" },
];

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
      renderSearchBox(),
      h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
      h("a", { class: "button button--on-dark button--small site-header__login", href: loginUrl() }, "Connexion"),
    ),
  );
}
