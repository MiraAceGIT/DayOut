import { isApiError, messageFor } from "../core/errors.js";
import { currentPosition } from "../core/geolocation.js";
import { MAX_EVENTS_LIMIT, fetchUpcomingEvents, remainingPlaces } from "../models/Event.js";
import { categoriesIn } from "../models/category.js";
import { filterEvents } from "../models/eventFilters.js";
import { HomeView } from "../views/home/HomeView.js";
import { MapView } from "../views/home/MapView.js";

/** Point par defaut de la maquette. */
export const DEFAULT_ORIGIN = { lat: 48.8455, lng: 2.3275, label: "ISEP – Notre-Dame-des-Champs" };
export const DEFAULT_RADIUS_KM = 3;

const LOCATE_ERRORS = {
  denied: "Tu as refusé l'accès à ta position. Clique sur la carte pour choisir un point.",
  unsupported: "Ton navigateur ne permet pas de te localiser. Clique sur la carte pour choisir un point.",
  timeout: "Ta position met trop de temps à arriver. Réessaie ou clique sur la carte.",
  unavailable: "Ta position est introuvable pour le moment. Clique sur la carte pour choisir un point.",
};

/** Carte "Que faire autour de toi ?" : etat des filtres, chargement, synchronisation liste <-> carte. */
export class HomeController {
  constructor({ view = new HomeView(), map = new MapView() } = {}) {
    this.view = view;
    this.map = map;
    this.events = [];
    this.status = "loading"; // "loading" | "ready" | "error" : pas de rendu de liste hors "ready"
    this.truncated = false;
    this.selectedId = null;
    // Incremente a chaque choix de point (clic ou localisation) : une position arrivee en retard est ignoree.
    this.pickSeq = 0;
    this.criteria = {
      origin: { ...DEFAULT_ORIGIN },
      radiusKm: DEFAULT_RADIUS_KM,
      weekend: false,
      free: false,
      available: false,
      categories: new Set(),
      query: "",
    };
  }

  async start({ query = "" } = {}) {
    if (!this.view.isReady) return;
    this.criteria.query = query;

    this.view.bind({
      onSearch: (q) => this.update({ query: q }),
      onRadius: (km) => this.setRadius(km),
      onFilter: (name, on) => this.update({ [name]: on }),
      onCategory: (slug, on) => this.toggleCategory(slug, on),
      onSelect: (id) => this.select(id, { from: "list" }),
      onChangePoint: () => this.promptPick(),
    });
    this.map.init({
      origin: this.criteria.origin,
      radiusKm: this.criteria.radiusKm,
      handlers: {
        onPick: (point) => this.pick(point),
        onSelect: (id) => this.select(id, { from: "map" }),
        onLocate: () => this.locate(),
      },
    });
    this.view.setQuery(query);
    this.view.setOrigin(this.criteria.origin);
    this.view.setRadius(this.criteria.radiusKm);

    this.view.showLoading();
    try {
      this.events = await fetchUpcomingEvents({ limit: MAX_EVENTS_LIMIT });
      // L'API n'a pas de filtre de date : au plafond, des evenements a venir peuvent manquer.
      this.truncated = this.events.length >= MAX_EVENTS_LIMIT;
      this.status = "ready";
      const categories = categoriesIn(this.events);
      this.view.renderCategoryFilters(categories, this.criteria.categories);
      this.map.renderLegend(categories);
      this.render();
    } catch (err) {
      // Une ApiError est un echec attendu (reseau, HTTP...) ; toute autre erreur est un bug, a garder en console.
      if (!isApiError(err)) console.error(err);
      this.status = "error";
      this.view.showError(messageFor(err));
    } finally {
      this.view.endLoading();
    }
  }

  update(changes) {
    Object.assign(this.criteria, changes);
    this.render();
  }

  /** Les criteres sont toujours memorises ; la liste n'est dessinee qu'une fois les donnees chargees. */
  render() {
    if (this.status !== "ready") return;
    const result = filterEvents(this.events, this.criteria);
    result.items = result.items.map((item) => ({ ...item, remaining: remainingPlaces(item.event) }));
    result.loadedCap = this.truncated ? this.events.length : 0;
    if (!result.items.some((item) => item.event.id === this.selectedId)) this.selectedId = null;
    this.view.showResults(result, this.selectedId);
    this.map.showEvents(result.items, this.selectedId);
  }

  setRadius(km) {
    this.view.setRadius(km);
    this.map.setRadius(km);
    this.update({ radiusKm: km });
  }

  toggleCategory(slug, on) {
    const categories = new Set(this.criteria.categories);
    if (on) categories.add(slug);
    else categories.delete(slug);
    this.update({ categories });
  }

  select(id, { from }) {
    this.selectedId = id;
    this.view.markSelected(id, { scroll: from === "map" });
    this.map.select(id, { openPopup: true });
  }

  /** Choix manuel (clic, Entree) : invalide toute localisation en cours. */
  pick(point) {
    this.pickSeq += 1;
    this.map.showMessage("");
    this.applyOrigin(point);
  }

  applyOrigin(point) {
    const origin = { lat: point.lat, lng: point.lng, label: point.label ?? "" };
    this.view.setOrigin(origin);
    this.map.setOrigin(origin, this.criteria.radiusKm);
    this.update({ origin });
  }

  promptPick() {
    if (!this.map.isAvailable) {
      this.map.showMessage("La carte est indisponible : le point ne peut pas être changé pour le moment.");
      return;
    }
    this.map.focus();
    this.map.showMessage("Clique sur la carte pour déplacer le point, ou appuie sur Entrée pour choisir son centre.");
  }

  async locate() {
    const seq = ++this.pickSeq;
    this.map.showMessage("Recherche de ta position…");
    try {
      const position = await currentPosition();
      if (seq !== this.pickSeq) return; // un autre point a ete choisi entre-temps
      this.applyOrigin({ ...position, label: "Ta position" });
      this.map.showMessage("Point placé sur ta position.");
    } catch (err) {
      if (seq !== this.pickSeq) return;
      this.map.showMessage(LOCATE_ERRORS[err?.code] ?? LOCATE_ERRORS.unavailable);
    }
  }
}
