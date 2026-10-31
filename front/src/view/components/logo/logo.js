import { h } from "../../../shared/dom.js";
import { frontUrl } from "../../../core/config.js";

const LOGO_LIGHT = "assets/img/dayout-logo-light.svg";
const LOGO_RATIO = 591.8 / 258; // viewBox du SVG

/** `width` en px : 96 minimum (charte). */
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
