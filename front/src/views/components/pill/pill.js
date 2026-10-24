import { h } from "../../../lib/dom.js";
import { formatPrice, pluralize } from "../../../lib/format.js";
import { categoryClass, renderCategoryDot } from "../category/category.js";

/** `category` = { slug, label } normalise par toCategory() ; null -> rien. */
export function renderCategoryPill(category) {
  if (!category) return null;
  return h("span", { class: ["pill", categoryClass(category)] },
    renderCategoryDot(),
    category.label,
  );
}

/** "Gratuit" (vert) ou "Payant · 12 €" (orange texte). Le texte reste en casse normale : le CSS met en majuscules. */
export function renderPricePill(event) {
  if (event.isPaid === false) {
    return h("span", { class: "pill pill--success" }, "Gratuit");
  }
  const price = formatPrice(event.priceCents, { currency: event.currency });
  return h("span", { class: "pill pill--price" }, price ? `Payant · ${price}` : "Payant");
}

/**
 * D'apres `event.availability` (toEvent) : complet (rouge), dernieres places (jaune fonce) ;
 * places en nombre, pastille neutre seulement si `showPlenty` ; capacite illimitee -> rien.
 */
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
