/** `raw` = { slug, label } de l'API -> { slug, label } ; libelle affiche tel que renvoye. */
export function toCategory(raw) {
  const slug = String(raw?.slug ?? "");
  return {
    slug,
    label: String(raw?.label ?? slug),
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
