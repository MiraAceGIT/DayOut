import { h } from "../../../lib/dom.js";
import { frontUrl } from "../../../core/config.js";

// Seul endroit qui connait le fichier du logo (header et footer, tous deux sur fond violet nuit).
const LOGO_LIGHT = "assets/img/dayout-logo-light.svg";
const LOGO_RATIO = 591.8 / 258; // viewBox du SVG

/** Logo cliquable vers l'accueil ; `width` en px (charte : 96 px minimum). */
export function renderLogo({ width = 110, className = "" } = {}) {
  return h("a", { class: ["logo", className], href: frontUrl("index.html").href },
    h("img", {
      class: "logo__img",
      src: frontUrl(LOGO_LIGHT).href,
      alt: "DayOut",
      width: Math.max(96, width),
      height: Math.round(Math.max(96, width) / LOGO_RATIO),
    }),
  );
}
