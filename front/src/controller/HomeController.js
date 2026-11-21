import { MAX_PAGE_LIMIT } from "../core/apiLimits.js";
import { isApiError, messageFor } from "../core/errors.js";
import { currentPosition } from "../core/geolocation.js";
import { COVERAGE_AREA } from "../model/domain/coverageArea.js";
import { cleanKeyword } from "../model/domain/keyword.js";
import { normalizePageLimit } from "../model/mappers/pageQueryMapper.js";
import { fetchUpcomingEvents } from "../model/services/eventService.js";
import { MapSearch } from "../model/state/MapSearch.js";
import { SearchBox } from "../view/components/searchBox/searchBox.js";
import { HomeView } from "../view/home/HomeView.js";
import { MapView } from "../view/home/map/MapView.js";
import { LatestTask } from "../shared/LatestTask.js";
import { OriginPicker } from "./OriginPicker.js";

/**
 * Carte : recherche par mot-cle cote API (seule la plus recente compte), puis rayon, filtres et selection
 * en local (MapSearch) ; le choix du point de depart est delegue a OriginPicker.
 */
export class HomeController {
  constructor({
    view = new HomeView(),
    map = new MapView(),
    searchBox = new SearchBox(),
    state = new MapSearch(),
    loadEvents = fetchUpcomingEvents,
    getPosition = currentPosition,
    limit = MAX_PAGE_LIMIT,
  } = {}) {
    this.view = view;
    this.map = map;
    this.searchBox = searchBox;
    this.state = state;
    this.loadEvents = loadEvents;
    // L'API plafonne `limit` : le seuil de troncature doit etre la limite effective, pas celle demandee.
    this.limit = normalizePageLimit(limit);
    this.searches = new LatestTask();
    this.origin = new OriginPicker({ map, getPosition, onOrigin: (point) => this.applyOrigin(point) });
    this.keyword = "";
    this.started = false;
    /** @type {(name: string, value: string) => void} */
    this.setParam = () => {};
  }

  /**
   * @param {{ query?: string, signal?: AbortSignal, setParam?: (name: string, value: string) => void }} [options]
   * `signal` : annule quand on quitte la carte ; `setParam` : met a jour l'adresse (?q=) par le routeur.
   */
  async start({ query = "", signal, setParam = () => {} } = {}) {
    if (!this.view.isReady || this.started || signal?.aborted) return;
    this.started = true;
    this.setParam = setParam;
    this.keyword = cleanKeyword(query);
    const { origin, radiusKm } = this.state.criteria;

    this.view.bind({
      onRadius: (km) => this.setRadius(km),
      onFilter: (name, on) => this.setFlag(name, on),
      onCategory: (slug, on) => this.toggleCategory(slug, on),
      onSelect: (id) => this.select(id, { from: "list" }),
      onChangePoint: () => this.map.promptPick(),
    }, signal);
    this.searchBox.bindSubmit((q) => this.search(q), { signal });
    this.map.init({
      origin,
      radiusKm,
      area: COVERAGE_AREA,
      signal,
      handlers: {
        onPick: (point) => this.origin.pick(point),
        onSelect: (id) => this.select(id, { from: "map" }),
        onLocate: () => this.origin.locate(),
      },
    });
    // On quitte la carte : Leaflet est detruit, une localisation ou une recherche en cours est ignoree.
    signal?.addEventListener("abort", () => {
      this.origin.cancel();
      this.searches.cancel();
      this.map.destroy();
    });
    this.searchBox.setQuery(this.keyword);
    this.view.setOrigin(origin);
    this.view.setRadius(radiusKm);

    await this.load();
  }

  /** Recherche par mot-cle cote API (vide = tous les evenements a venir) ; rayon et filtres s'appliquent ensuite en local. */
  async load() {
    const search = this.searches.start();
    const keyword = this.keyword;

    this.view.showLoading();
    try {
      const events = await this.loadEvents({ limit: this.limit, query: keyword, signal: search.signal });
      if (!search.isCurrent()) return;
      this.state.load(events, { limit: this.limit, keyword });
      const { categories } = this.state;
      this.view.renderCategoryFilters(categories, this.state.criteria.categories);
      this.map.renderLegend(categories);
      this.render();
    } catch (err) {
      // Recherche remplacee, ou carte quittee (requete annulee) : la vue n'est plus a mettre a jour.
      if (!search.isCurrent()) return;
      // ApiError = echec attendu ; toute autre erreur est un bug.
      if (!isApiError(err)) console.error(err);
      this.state.fail();
      this.view.showError(messageFor(err));
    } finally {
      if (search.isCurrent()) this.view.endLoading();
    }
  }

  /** Les criteres sont toujours memorises ; la liste n'est dessinee qu'une fois les donnees chargees. */
  render() {
    if (!this.state.isReady) return;
    const result = this.state.results();
    this.view.showResults(result, this.state.selectedId);
    this.view.showSuggestions(this.state.suggestions());
    this.map.showEvents(result.items, this.state.selectedId);
  }

  search(query) {
    const keyword = cleanKeyword(query);
    if (keyword === this.keyword && this.state.isReady) return Promise.resolve();
    this.keyword = keyword;
    this.setParam("q", keyword);
    return this.load();
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

  /** Point accepte par OriginPicker (dans la zone couverte). */
  applyOrigin(point) {
    const origin = this.state.setOrigin(point);
    this.view.setOrigin(origin);
    this.map.setOrigin(origin, this.state.criteria.radiusKm);
    this.render();
  }
}
