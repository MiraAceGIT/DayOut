import { h } from "../../../shared/dom.js";

/** Seul lien slug API -> couleur de la charte ; les slugs absents d'ici prennent la couleur neutre. */
const COLOR_KEY_BY_SLUG = new Map([
  ["soiree", "soiree"],
  ["sport", "sport"],
  ["conference", "conference"],
  ["tech", "hackathon"],
  ["hackathon", "hackathon"],
  ["culture", "culture"],
  ["forum-entreprises", "forum"],
]);

export function hasCharterColor(category) {
  return COLOR_KEY_BY_SLUG.has(category?.slug);
}

export function categoryClass(category) {
  const key = COLOR_KEY_BY_SLUG.get(category?.slug);
  return key ? `cat--${key}` : null;
}

/** Premiere categorie de la charte, sinon la premiere : l'API trie par libelle, "premiere" seule serait alphabetique. */
export function colorCategoryOf(event) {
  return event.categories.find(hasCharterColor) ?? event.categories[0] ?? null;
}

/** Point decoratif a la couleur de la categorie : le libelle a cote porte l'information. */
export function renderCategoryDot() {
  return h("span", { class: "cat-dot", "aria-hidden": "true" });
}
