export function normalize(text) {
  return String(text ?? "").normalize("NFD").replace(/[̀-ͯ]/g, "").toLowerCase().trim();
}

/** [samedi 0 h, lundi 0 h) du week-end en cours, ou du prochain en semaine. */
export function weekendRange(now = new Date()) {
  const start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const day = start.getDay(); // 0 = dimanche
  const offset = day === 0 ? -1 : 6 - day;
  start.setDate(start.getDate() + offset);
  const end = new Date(start);
  end.setDate(end.getDate() + 2);
  return { start, end };
}

export const isFree = (event) => event.isPaid === false;

export const hasPlacesLeft = (event) => event.availability !== "full";

export const inCategories = (slugs) => (event) => event.categories.some((c) => slugs.has(c.slug));

export const startsWithin = ({ start, end }) => (event) => {
  const date = new Date(event.startAt);
  return date >= start && date < end;
};

export const matchesText = (query) => {
  const needle = normalize(query);
  return (event) => [event.title, event.venueName, event.city].some((v) => normalize(v).includes(needle));
};

/** Chaque entree renvoie un predicat, ou null si le critere est inactif. */
export const USER_FILTERS = [
  ({ free }) => (free ? isFree : null),
  ({ available }) => (available ? hasPlacesLeft : null),
  ({ categories }) => (categories?.size > 0 ? inCategories(categories) : null),
  ({ weekend, now = new Date() }) => (weekend ? startsWithin(weekendRange(now)) : null),
  ({ query }) => (normalize(query) ? matchesText(query) : null),
];

export function matchesCriteria(criteria, filters = USER_FILTERS) {
  const predicates = filters.map((toPredicate) => toPredicate(criteria)).filter(Boolean);
  return (event) => predicates.every((test) => test(event));
}
