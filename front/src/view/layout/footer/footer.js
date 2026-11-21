import { h } from "../../../shared/dom.js";
import { routeUrl } from "../../../core/routes.js";
import { renderLogo } from "../../components/logo/logo.js";

// Pages pas encore faites : "Page introuvable" en attendant.
const FOOTER_LINKS = [
  { label: "FAQ",              path: "/faq" },
  { label: "CGU",              path: "/cgu" },
  { label: "Mentions légales", path: "/legal-notice" },
  { label: "Contact",          path: "/contact" },
  { label: "Confidentialité",  path: "/privacy" },
  { label: "À propos",         path: "/about" },
];

export function renderFooter() {
  const host = document.querySelector("footer[data-layout]");
  if (!host) return;

  host.classList.add("site-footer");
  host.replaceChildren(
    h("div", { class: "container site-footer__inner" },
      h("div", { class: "site-footer__brand" },
        renderLogo({ width: 96, className: "site-footer__logo" }),
        h("p", {}, "Les sorties étudiantes inter-écoles, autour de toi."),
      ),
      h("nav", { "aria-label": "Informations" },
        h("ul", { class: "site-footer__links" },
          FOOTER_LINKS.map((item) => h("li", {}, h("a", { class: "site-footer__link", href: routeUrl(item.path) }, item.label))),
        ),
      ),
      h("p", { class: "site-footer__credit" }, `© ${new Date().getFullYear()} DayOut · Réalisé par G4B Digital`),
    ),
  );
}
