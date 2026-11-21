import { h } from "../../../shared/dom.js";
import { categoryClass, hasCharterColor, renderCategoryDot } from "../../components/category/category.js";

export class MapLegend {
  constructor(legendEl) {
    this.legendEl = legendEl;
  }

  // Les categories sans couleur ont toutes le meme gris : une seule entree "Autres".
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
