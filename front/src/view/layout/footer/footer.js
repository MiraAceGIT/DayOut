import { h } from "../../../shared/dom.js";
import { renderLogo } from "../../components/logo/logo.js";

// TODO : liens FAQ / CGU / Mentions legales / Contact quand ces pages existeront (pas de lien casse d'ici la).
export function renderFooter() {
  const host = document.querySelector("footer[data-layout]");
  if (!host) return;

  host.classList.add("site-footer");
  host.replaceChildren(
    h("div", { class: "container site-footer__inner" },
      renderLogo({ width: 96, className: "site-footer__logo" }),
      h("p", { class: "site-footer__tagline" }, "Les sorties étudiantes inter-écoles, autour de toi."),
      h("p", { class: "site-footer__credit" }, "Réalisé par G4B Digital"),
    ),
  );
}
