import { isApiError, messageFor } from "../core/errors.js";
import { currentPosition } from "../core/geolocation.js";
import { replaceSearchParam } from "../core/routes.js";
import { MAX_EVENTS_LIMIT, fetchUpcomingEvents } from "../dao/eventDao.js";
import { MapSearch } from "../models/MapSearch.js";
import { SERVICE_AREA, isInServiceArea } from "../models/serviceArea.js";
import { HomeView } from "../views/home/HomeView.js";
import { MapView } from "../views/home/map/MapView.js";
import { SearchBox } from "../views/layout/header/searchBox.js";

/**
 * Carte "Que faire autour de toi ?" : relaie les actions des views vers l'etat (MapSearch)
 * puis redessine panneau et carte. Chargement, geolocalisation et ?q= sont orchestres ici.
 */
export class HomeController {
  constructor({
    view = new HomeView(),
    map = new MapView(),
    searchBox = new SearchBox(),
    state = new MapSearch(),
    loadEvents = fetchUpcomingEvents,
    getPosition = currentPosition,
    limit = MAX_EVENTS_LIMIT,
  } = {}) {
    this.view = view;
    this.map = map;
    this.searchBox = searchBox;
    this.state = state;
    this.loadEvents = loadEvents;
    this.getPosition = getPosition;
    // L'API plafonne `limit` : le seuil de troncature doit etre la limite effective, pas celle demandee.
    this.limit = Math.min(limit, MAX_EVENTS_LIMIT);
    // Incremente a chaque choix de point (clic ou localisation) : une position arrivee en retard est ignoree.
    this.pickSeq = 0;
  }

  async start({ query = "" } = {}) {
    if (!this.view.isReady) return;
    this.state.setQuery(query);
    const { origin, radiusKm } = this.state.criteria;

    this.view.bind({
      onRadius: (km) => this.setRadius(km),
      onFilter: (name, on) => this.setFlag(name, on),
      onCategory: (slug, on) => this.toggleCategory(slug, on),
      onSelect: (id) => this.select(id, { from: "list" }),
      onChangePoint: () => this.map.promptPick(),
    });
    this.searchBox.bindLive((q) => this.search(q));
    this.map.init({
      origin,
      radiusKm,
      area: SERVICE_AREA,
      handlers: {
        onPick: (point) => this.pick(point),
        onSelect: (id) => this.select(id, { from: "map" }),
        onLocate: () => this.locate(),
      },
    });
    this.searchBox.setQuery(query);
    this.view.setOrigin(origin);
    this.view.setRadius(radiusKm);

    this.view.showLoading();
    try {
      this.state.load(await this.loadEvents({ limit: this.limit }), { limit: this.limit });
      const { categories } = this.state;
      this.view.renderCategoryFilters(categories, this.state.criteria.categories);
      this.map.renderLegend(categories);
      this.render();
    } catch (err) {
      // Une ApiError est un echec attendu (reseau, HTTP...) ; toute autre erreur est un bug, a garder en console.
      if (!isApiError(err)) console.error(err);
      this.state.fail();
      this.view.showError(messageFor(err));
    } finally {
      this.view.endLoading();
    }
  }

  /** Les criteres sont toujours memorises ; la liste n'est dessinee qu'une fois les donnees chargees. */
  render() {
    if (!this.state.isReady) return;
    const result = this.state.results();
    this.view.showResults(result, this.state.selectedId);
    this.map.showEvents(result.items, this.state.selectedId);
  }

  search(query) {
    this.state.setQuery(query);
    this.render();
    replaceSearchParam("q", query);
  }

  setRadius(km) {
    this.state.setRadius(km);
    this.view.setRadius(km);
    this.map.setRadius(km);
    this.render();
  }

  setFlag(name, on) {
    this.state.setFlag(name, on);
    this.render();
  }

  toggleCategory(slug, on) {
    this.state.toggleCategory(slug, on);
    this.render();
  }

  select(id, { from }) {
    this.state.select(id);
    this.view.markSelected(id, { scroll: from === "map" });
    this.map.select(id, { openPopup: true });
  }

  /** Choix manuel (clic, Entree) : invalide toute localisation en cours. */
  pick(point) {
    this.pickSeq += 1;
    // La carte laisse voir une marge autour de la zone : un clic dans cette marge garde l'ancien point.
    if (!isInServiceArea(point)) {
      this.map.showOutOfArea(SERVICE_AREA.name, "map");
      return;
    }
    this.map.clearMessage();
    this.applyOrigin(point);
  }

  applyOrigin(point) {
    const origin = this.state.setOrigin(point);
    this.view.setOrigin(origin);
    this.map.setOrigin(origin, this.state.criteria.radiusKm);
    this.render();
  }

  async locate() {
    const seq = ++this.pickSeq;
    this.map.showLocating();
    try {
      const position = await this.getPosition();
      if (seq !== this.pickSeq) return; // un autre point a ete choisi entre-temps
      if (!isInServiceArea(position)) {
        this.map.showOutOfArea(SERVICE_AREA.name, "device");
        return;
      }
      this.applyOrigin({ ...position, source: "device" });
      this.map.showLocated();
    } catch (err) {
      if (seq !== this.pickSeq) return;
      this.map.showLocateError(err?.code);
    }
  }
}
