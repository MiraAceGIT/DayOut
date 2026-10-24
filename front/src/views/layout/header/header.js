import { h } from "../../../lib/dom.js";
import { frontUrl } from "../../../core/config.js";

export const NAV_ITEMS = [
  { id: "home",   label: "Accueil",    path: "index.html" },
  { id: "events", label: "Evenements", path: "pages/events.html" },
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
    h("div", { class: "container site-header__inner" },
      h("a", { class: "site-header__logo", href: frontUrl("index.html").href }, "DayOut"),
      h("nav", { class: "site-nav", "aria-label": "Navigation principale" }, links),
    ),
  );
}
