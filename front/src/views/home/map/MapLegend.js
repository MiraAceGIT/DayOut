import { h } from "../../../shared/dom.js";
import { categoryClass, hasCharterColor, renderCategoryDot } from "../../components/category/category.js";

/** Legende "Categories" (DOM simple, independant de Leaflet). */
export class MapLegend {
  constructor(legendEl) {
    this.legendEl = legendEl;
  }

  /**
   * Depuis les categories presentes ([{ slug, label }]) : une ligne par couleur de la charte,
   * les categories sans couleur regroupees sous "Autres" (meme gris, indistinguables).
   */
  render(categories) {
    if (!this.legendEl) return;
    const item = (className, label) => h("li", { class: ["map-legend__item", className] }, renderCategoryDot(), label);
    const coloured = categories.filter(hasCharterColor);
    const items = coloured.map((c) => item(categoryClass(c), c.label));
    if (coloured.length < categories.length) items.push(item(null, "Autres"));
    this.legendEl.querySelector("ul")?.replaceChildren(...items);
    this.legendEl.hidden = categories.length === 0;
  }
}
