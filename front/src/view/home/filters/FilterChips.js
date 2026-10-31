import { h } from "../../../shared/dom.js";
import { categoryClass, renderCategoryDot } from "../../components/category/category.js";

/** Inverse aria-pressed et renvoie le nouvel etat. */
function toggle(button) {
  const on = button.getAttribute("aria-pressed") !== "true";
  button.setAttribute("aria-pressed", String(on));
  return on;
}

/** Puces a bascule : filtres fixes du HTML (`data-filter`) et categories construites depuis les donnees. */
export class FilterChips {
  constructor({ filtersEl, categoriesEl }) {
    this.filtersEl = filtersEl;
    this.categoriesEl = categoriesEl;
    this.onCategory = () => {};
  }

  get isReady() {
    return Boolean(this.filtersEl && this.categoriesEl);
  }

  /** `handlers` : onFilter(name, on), onCategory(slug, on). */
  bind({ onFilter, onCategory }) {
    this.onCategory = onCategory;
    this.filtersEl.addEventListener("click", (e) => {
      const chip = e.target.closest("button[data-filter]");
      if (!chip) return;
      onFilter(chip.dataset.filter, toggle(chip));
    });
  }

  /** `categories` = [{ slug, label }] ; `active` = Set des slugs deja choisis. */
  renderCategories(categories, active = new Set()) {
    this.categoriesEl.replaceChildren(...categories.map((category) =>
      h("button", {
        type: "button",
        class: ["chip chip--category", categoryClass(category)],
        "aria-pressed": String(active.has(category.slug)),
        onClick: (e) => this.onCategory(category.slug, toggle(e.currentTarget)),
      },
        renderCategoryDot(),
        category.label,
      )
    ));
    this.categoriesEl.hidden = categories.length === 0;
  }
}
