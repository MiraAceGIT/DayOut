/**
 * Seul endroit qui rattache un slug API a une couleur de la charte (cle `cat--<key>` de
 * assets/css/components/category.css). Le referentiel du back (infra/sql/seed.sql) ne recoupe la charte
 * qu'en partie : les slugs absents d'ici (concert, gaming, bde, exterieur, gastronomie) prennent la
 * couleur neutre. "forum-entreprises" n'existe pas encore en base mais garde sa couleur pour plus tard.
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

/** `raw` = { slug, label } de l'API -> { slug, label, key } ; libelle affiche tel que renvoye, `key` null = neutre. */
export function toCategory(raw) {
  const slug = String(raw?.slug ?? "");
  return {
    slug,
    label: String(raw?.label ?? slug),
    key: COLOR_KEY_BY_SLUG.get(slug) ?? null,
  };
}

/** Categories distinctes presentes dans `events`, triees par libelle (filtres et legende de la carte). */
export function categoriesIn(events) {
  const bySlug = new Map();
  for (const event of events) {
    for (const category of event.categories) {
      if (category.slug && !bySlug.has(category.slug)) bySlug.set(category.slug, category);
    }
  }
  return [...bySlug.values()].sort((a, b) => a.label.localeCompare(b.label, "fr"));
}
