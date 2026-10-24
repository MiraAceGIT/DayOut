import { h } from "../../../lib/dom.js";

/**
 * Seul endroit qui rattache un slug API a une couleur de la charte (cle `cat--<key>` de category.css).
 * Le referentiel du back (infra/sql/seed.sql) ne recoupe la charte qu'en partie : les slugs absents d'ici
 * (concert, gaming, bde, exterieur, gastronomie) prennent la couleur neutre. "forum-entreprises" n'existe
 * pas encore en base mais garde sa couleur pour plus tard.
 */
const COLOR_KEY_BY_SLUG = new Map([
  ["soiree", "soiree"],
  ["sport", "sport"],
  ["conference", "conference"],
  ["tech", "hackathon"],
  ["hackathon", "hackathon"],
  ["culture", "culture"],
  ["forum-entreprises", "forum"],
]);

/** Vrai si la categorie a une couleur de la charte (sinon gris neutre). */
export function hasCharterColor(category) {
  return COLOR_KEY_BY_SLUG.has(category?.slug);
}

/** Classe `cat--<key>` qui pose --cat-color ; null = couleur neutre. */
export function categoryClass(category) {
  const key = COLOR_KEY_BY_SLUG.get(category?.slug);
  return key ? `cat--${key}` : null;
}

/**
 * Categorie qui colore un evenement (carre, marqueur, pastille) : premiere categorie de la charte, sinon la premiere.
 * L'API trie par libelle, donc "premiere" seule serait alphabetique (une soiree "BDE / Assoc" + "Soiree" sortirait grise).
 */
export function colorCategoryOf(event) {
  return event.categories.find(hasCharterColor) ?? event.categories[0] ?? null;
}

/** Point decoratif a la couleur de la categorie : le libelle a cote porte l'information. */
export function renderCategoryDot() {
  return h("span", { class: "cat-dot", "aria-hidden": "true" });
}
