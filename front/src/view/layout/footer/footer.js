import { h } from "../../../shared/dom.js";
import { PATHS, routeHref } from "../../../core/routes.js";
import { renderLogo } from "../../components/logo/logo.js";

export const FOOTER_LINKS = [
  { label: "FAQ",              path: PATHS.faq },
  { label: "CGU",              path: PATHS.terms },
  { label: "Mentions légales", path: PATHS.legal },
  { label: "Contact",          path: PATHS.contact },
];

export function renderFooter() {
  const host = document.querySelector("footer[data-layout]");
  if (!host) return;

  host.classList.add("site-footer");
  host.replaceChildren(
    h("div", { class: "container site-footer__inner" },
      h("div", { class: "site-footer__about" },
        renderLogo({ width: 96, className: "site-footer__logo" }),
        h("p", { class: "site-footer__tagline" }, "Les sorties étudiantes des écoles d'ingénieurs, au même endroit."),
      ),
      h("nav", { class: "site-footer__nav", "aria-label": "Liens utiles" },
        h("ul", { class: "site-footer__links" },
          FOOTER_LINKS.map((link) =>
            h("li", {}, h("a", { class: "site-footer__link", href: routeHref(link.path) }, link.label))
          ),
        ),
      ),
      h("p", { class: "site-footer__credit" }, "Un projet G4B Digital · ISEP · APP Informatique 2026/2027"),
    ),
  );
}
