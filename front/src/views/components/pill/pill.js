import { h } from "../../../lib/dom.js";
import { formatPrice } from "../../../lib/format.js";

/** Seuil "dernieres places" (couleur --color-warning). */
export const LAST_PLACES_THRESHOLD = 5;

function placesLabel(n) {
  return `${n} place${n > 1 ? "s" : ""}`;
}

/** `category` = { key, label } normalise par toCategory() ; null -> rien. */
export function renderCategoryPill(category) {
  if (!category) return null;
  return h("span", { class: ["pill", category.key && `cat--${category.key}`] },
    h("span", { class: "cat-dot", "aria-hidden": "true" }),
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
 * `remaining` = places restantes (null = illimite). Complet (rouge), dernieres places (jaune fonce) ;
 * au-dela du seuil, pastille neutre seulement si `showPlenty`.
 */
export function renderPlacesPill(remaining, { showPlenty = false } = {}) {
  if (remaining === null || remaining === undefined) return null;
  if (remaining === 0) return h("span", { class: "pill pill--error" }, "Complet");
  if (remaining <= LAST_PLACES_THRESHOLD) return h("span", { class: "pill pill--warning" }, placesLabel(remaining));
  return showPlenty ? h("span", { class: "pill pill--neutral" }, placesLabel(remaining)) : null;
}
