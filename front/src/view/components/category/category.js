import { h } from "../../../shared/dom.js";

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

// L'API trie les categories par libelle : la premiere seule serait la premiere par ordre alphabetique.
export function colorCategoryOf(event) {
  return event.categories.find(hasCharterColor) ?? event.categories[0] ?? null;
}

export function renderCategoryDot() {
  return h("span", { class: "cat-dot", "aria-hidden": "true" });
}
