import { categoriesIn } from "../domain/category.js";
import { searchEvents } from "../domain/eventSearch.js";

/** @typedef {{ lat: number, lng: number, label?: string, source: string }} Origin `source` : default | map | device */

/**
 * Point par defaut de la maquette (ISEP Notre-Dame-des-Champs) ; son libelle est choisi par la vue.
 * @type {Origin}
 */
export const DEFAULT_ORIGIN = { lat: 48.8455, lng: 2.3275, source: "default" };
export const DEFAULT_RADIUS_KM = 3;

const FLAGS = new Set(["weekend", "free", "available"]);

/**
 * Etat de la page carte, sans DOM : evenements charges, criteres, selection.
 * Les criteres sont modifiables avant la fin du chargement ; `results()` n'a de sens qu'une fois `isReady`.
 */
export class MapSearch {
  constructor({ origin = DEFAULT_ORIGIN, radiusKm = DEFAULT_RADIUS_KM, query = "" } = {}) {
    this.status = "loading"; // "loading" | "ready" | "error" : pas de rendu de liste hors "ready"
    this.events = [];
    this.truncated = false;
    this.selectedId = null;
    this.criteria = {
      origin: { ...origin },
      radiusKm,
      weekend: false,
      free: false,
      available: false,
      categories: new Set(), // slugs
      query,
    };
  }

  get isReady() {
    return this.status === "ready";
  }

  /** `limit` demande a l'API : l'atteindre signifie que des evenements a venir peuvent manquer (pas de filtre de date). */
  load(events, { limit }) {
    this.events = events;
    this.truncated = events.length >= limit;
    this.status = "ready";
  }

  fail() {
    this.status = "error";
  }

  get categories() {
    return categoriesIn(this.events);
  }

  setQuery(query) {
    this.criteria.query = query;
  }

  setRadius(km) {
    this.criteria.radiusKm = km;
  }

  setFlag(name, on) {
    if (FLAGS.has(name)) this.criteria[name] = Boolean(on);
    else console.warn(`MapSearch: filtre inconnu "${name}" (ajouter sa cle a FLAGS).`);
  }

  toggleCategory(slug, on) {
    const categories = new Set(this.criteria.categories);
    if (on) categories.add(slug);
    else categories.delete(slug);
    this.criteria.categories = categories;
  }

  /** `point` = { lat, lng, label?, source? } ; `source` "device" = position de l'appareil. Renvoie le point retenu. */
  setOrigin(point) {
    this.criteria.origin = { lat: point.lat, lng: point.lng, label: point.label ?? "", source: point.source ?? "map" };
    return this.criteria.origin;
  }

  select(id) {
    this.selectedId = id;
  }

  /**
   * Resultats des criteres courants ; une selection qui n'en fait plus partie est oubliee.
   * `loadedCap` : nombre charge si le plafond de l'API est atteint, 0 sinon.
   */
  results({ now = new Date() } = {}) {
    const result = searchEvents(this.events, { ...this.criteria, now });
    if (!result.items.some((item) => item.event.id === this.selectedId)) this.selectedId = null;
    return { ...result, loadedCap: this.truncated ? this.events.length : 0 };
  }
}
