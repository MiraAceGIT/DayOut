import { h } from "../../../shared/dom.js";
import { formatPrice, pluralize } from "../../../shared/format.js";
import { categoryClass, renderCategoryDot } from "../category/category.js";

export function renderCategoryPill(category) {
  if (!category) return null;
  return h("span", { class: ["pill", categoryClass(category)] },
    renderCategoryDot(),
    category.label,
  );
}

/** Texte en casse normale : le CSS met en majuscules. */
export function renderPricePill(event) {
  if (event.isPaid === false) {
    return h("span", { class: "pill pill--success" }, "Gratuit");
  }
  const price = formatPrice(event.priceCents, { currency: event.currency });
  return h("span", { class: "pill pill--price" }, price ? `Payant · ${price}` : "Payant");
}

export function renderPlacesPill(event, { showPlenty = false } = {}) {
  switch (event.availability) {
    case "full":
      return h("span", { class: "pill pill--error" }, "Complet");
    case "last":
      return h("span", { class: "pill pill--warning" }, pluralize(event.remainingPlaces, "place"));
    case "open":
      return showPlenty ? h("span", { class: "pill pill--neutral" }, pluralize(event.remainingPlaces, "place")) : null;
    default:
      return null;
  }
}
