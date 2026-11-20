import { h } from "../../../shared/dom.js";
import { formatPrice, formatRemainingPlaces } from "../../../shared/format.js";
import { highlight } from "../../../shared/highlight.js";
import { categoryClass, renderCategoryDot } from "../category/category.js";

export function renderCategoryPill(category, { keyword = "" } = {}) {
  if (!category) return null;
  return h("span", { class: ["pill", categoryClass(category)] },
    renderCategoryDot(),
    highlight(category.label, keyword),
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
      return h("span", { class: "pill pill--error" }, formatRemainingPlaces(event.remainingPlaces));
    case "last":
      return h("span", { class: "pill pill--warning" }, formatRemainingPlaces(event.remainingPlaces));
    case "open":
      return showPlenty ? h("span", { class: "pill pill--neutral" }, formatRemainingPlaces(event.remainingPlaces)) : null;
    default:
      return showPlenty && event.remainingPlaces === null
        ? h("span", { class: "pill pill--neutral" }, formatRemainingPlaces(null)) : null;
  }
}
