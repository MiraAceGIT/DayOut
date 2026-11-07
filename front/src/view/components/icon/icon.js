// Icones Lucide (trace 2 px) construites avec les API DOM SVG : h() cree des elements HTML, pas SVG.
// Donnees statiques uniquement : aucune donnee utilisateur ne passe ici.
const SVG_NS = "http://www.w3.org/2000/svg";

const ICONS = {
  search: [
    ["circle", { cx: 11, cy: 11, r: 8 }],
    ["path", { d: "m21 21-4.3-4.3" }],
  ],
  "map-pin": [
    ["path", { d: "M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" }],
    ["circle", { cx: 12, cy: 10, r: 3 }],
  ],
  menu: [
    ["line", { x1: 4, x2: 20, y1: 12, y2: 12 }],
    ["line", { x1: 4, x2: 20, y1: 6, y2: 6 }],
    ["line", { x1: 4, x2: 20, y1: 18, y2: 18 }],
  ],
  x: [
    ["path", { d: "M18 6 6 18" }],
    ["path", { d: "m6 6 12 12" }],
  ],
  "locate-fixed": [
    ["line", { x1: 2, x2: 5, y1: 12, y2: 12 }],
    ["line", { x1: 19, x2: 22, y1: 12, y2: 12 }],
    ["line", { x1: 12, x2: 12, y1: 2, y2: 5 }],
    ["line", { x1: 12, x2: 12, y1: 19, y2: 22 }],
    ["circle", { cx: 12, cy: 12, r: 7 }],
    ["circle", { cx: 12, cy: 12, r: 3 }],
  ],
};

function svgEl(tag, attrs) {
  const el = document.createElementNS(SVG_NS, tag);
  for (const [name, value] of Object.entries(attrs)) el.setAttribute(name, String(value));
  return el;
}

/** Icone decorative (aria-hidden) : le nom accessible doit venir du texte ou du controle qui la contient. */
export function icon(name, { size = 20, className = "icon" } = {}) {
  const svg = svgEl("svg", {
    viewBox: "0 0 24 24",
    width: size,
    height: size,
    fill: "none",
    stroke: "currentColor",
    "stroke-width": 2,
    "stroke-linecap": "round",
    "stroke-linejoin": "round",
    "aria-hidden": "true",
    focusable: "false",
    class: className,
  });
  for (const [tag, attrs] of ICONS[name] ?? []) svg.append(svgEl(tag, attrs));
  return svg;
}

export function pinIcon({ size = 32, className = "map-pin__svg" } = {}) {
  const svg = svgEl("svg", {
    viewBox: "0 0 24 24",
    width: size,
    height: size,
    "aria-hidden": "true",
    focusable: "false",
    class: className,
  });
  svg.append(
    // Contour et point clairs : couleur portee par map.css (tokens), pas d'attribut en dur.
    svgEl("path", {
      d: ICONS["map-pin"][0][1].d,
      class: "map-pin__outline",
      fill: "currentColor",
      "stroke-width": 1.5,
      "stroke-linejoin": "round",
    }),
    svgEl("circle", { cx: 12, cy: 10, r: 3, class: "map-pin__dot" }),
  );
  return svg;
}
