import { distanceKm, isLatLng } from "../lib/geo.js";
import { remainingPlaces } from "./Event.js";

/** Minuscules sans accents : "Soirée" et "soiree" se trouvent mutuellement. */
function normalize(text) {
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

/**
 * Filtres de la carte, appliques cote client aux evenements deja charges.
 * Les evenements termines (fin, ou debut a defaut, avant `now`) sont toujours exclus : l'API ne filtre pas par date.
 * `criteria` : { origin {lat,lng}, radiusKm, weekend, free, available, categories (Set de slugs), query, now }.
 * Renvoie { items: [{ event, distanceKm }], inCircle, withoutPosition } :
 * les evenements sans coordonnees passent les autres filtres mais ne comptent pas dans le cercle.
 */
export function filterEvents(events, {
  origin,
  radiusKm,
  weekend = false,
  free = false,
  available = false,
  categories = new Set(), // slugs
  query = "",
  now = new Date(),
} = {}) {
  const needle = normalize(query);
  const range = weekend ? weekendRange(now) : null;
  const items = [];
  let inCircle = 0;
  let withoutPosition = 0;

  const nowTime = now.getTime();
  for (const event of events) {
    const last = Date.parse(event.endAt ?? event.startAt);
    if (Number.isFinite(last) && last < nowTime) continue;
    if (free && event.isPaid !== false) continue;
    if (available && remainingPlaces(event) === 0) continue;
    if (categories.size > 0 && !event.categories.some((c) => categories.has(c.slug))) continue;
    if (range) {
      const start = new Date(event.startAt);
      if (!(start >= range.start && start < range.end)) continue;
    }
    if (needle && ![event.title, event.venueName, event.city].some((v) => normalize(v).includes(needle))) continue;

    if (isLatLng(event.position) && isLatLng(origin)) {
      const km = distanceKm(origin, event.position);
      if (km > radiusKm) continue;
      inCircle += 1;
      items.push({ event, distanceKm: km });
    } else {
      withoutPosition += 1;
      items.push({ event, distanceKm: null });
    }
  }

  const time = (item) => Date.parse(item.event.startAt) || 0;
  items.sort((a, b) => time(a) - time(b));
  return { items, inCircle, withoutPosition };
}
