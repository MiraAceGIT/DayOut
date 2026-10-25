export function categoriesIn(events) {
  const bySlug = new Map();
  for (const event of events) {
    for (const category of event.categories) {
      if (category.slug && !bySlug.has(category.slug)) bySlug.set(category.slug, category);
    }
  }
  return [...bySlug.values()].sort((a, b) => a.label.localeCompare(b.label, "fr"));
}
