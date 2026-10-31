import { h } from "../../../shared/dom.js";
import { categoryClass, renderCategoryDot } from "../../components/category/category.js";

function toggle(button) {
  const on = button.getAttribute("aria-pressed") !== "true";
  button.setAttribute("aria-pressed", String(on));
  return on;
}

export class FilterChips {
  constructor({ filtersEl, categoriesEl }) {
    this.filtersEl = filtersEl;
    this.categoriesEl = categoriesEl;
    this.onCategory = () => {};
  }

  get isReady() {
    return Boolean(this.filtersEl && this.categoriesEl);
  }

  bind({ onFilter, onCategory }) {
    this.onCategory = onCategory;
    this.filtersEl.addEventListener("click", (e) => {
      const chip = e.target.closest("button[data-filter]");
      if (!chip) return;
      onFilter(chip.dataset.filter, toggle(chip));
    });
  }

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
