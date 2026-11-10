import { isApiError, messageFor } from "../core/errors.js";
import { currentPosition } from "../core/geolocation.js";
import { replaceSearchParam } from "../core/routes.js";
import { MAX_EVENTS_LIMIT, MAX_KEYWORD_LENGTH, fetchUpcomingEvents } from "../model/services/eventService.js";
import { MapSearch } from "../model/state/MapSearch.js";
import { COVERAGE_AREA, isInCoverageArea } from "../model/domain/coverageArea.js";
import { HomeView } from "../view/home/HomeView.js";
import { MapView } from "../view/home/map/MapView.js";
import { SearchBox } from "../view/components/searchBox/searchBox.js";

const cleanKeyword = (query) => String(query ?? "").trim().slice(0, MAX_KEYWORD_LENGTH);

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
    // Meme principe pour les chargements : seule la recherche la plus recente met la liste a jour.
    this.loadSeq = 0;
    this.loadAbort = null;
    this.pageSignal = undefined;
    this.keyword = "";
  }

  /** @param {{ query?: string, signal?: AbortSignal }} [options] `signal` : annule quand on quitte la carte. */
  async start({ query = "", signal } = {}) {
    if (!this.view.isReady) return;
    this.pageSignal = signal;
    this.keyword = cleanKeyword(query);
    const { origin, radiusKm } = this.state.criteria;

    this.view.bind({
      onRadius: (km) => this.setRadius(km),
      onFilter: (name, on) => this.setFlag(name, on),
      onCategory: (slug, on) => this.toggleCategory(slug, on),
      onSelect: (id) => this.select(id, { from: "list" }),
      onChangePoint: () => this.map.promptPick(),
    });
    this.searchBox.bindSubmit((q) => this.search(q), { signal });
    this.map.init({
      origin,
      radiusKm,
      area: COVERAGE_AREA,
      handlers: {
        onPick: (point) => this.pick(point),
        onSelect: (id) => this.select(id, { from: "map" }),
        onLocate: () => this.locate(),
      },
    });
    // On quitte la carte : Leaflet est detruit, une localisation ou une recherche en cours est ignoree.
    signal?.addEventListener("abort", () => {
      this.pickSeq += 1;
      this.loadAbort?.abort();
      this.map.destroy();
    });
    this.searchBox.setQuery(this.keyword);
    this.view.setOrigin(origin);
    this.view.setRadius(radiusKm);

    await this.load();
  }

  /** Recherche par mot-cle cote API (vide = tous les evenements a venir) ; rayon et filtres s'appliquent ensuite en local. */
  async load() {
    const seq = ++this.loadSeq;
    this.loadAbort?.abort(); // une recherche plus recente remplace celle en cours
    const controller = new AbortController();
    this.loadAbort = controller;
    const keyword = this.keyword;

    this.view.showLoading();
    try {
      const events = await this.loadEvents({ limit: this.limit, query: keyword, signal: controller.signal });
      if (seq !== this.loadSeq || this.pageSignal?.aborted) return; // recherche remplacee ou carte quittee
      this.state.load(events, { limit: this.limit, keyword });
      const { categories } = this.state;
      this.view.renderCategoryFilters(categories, this.state.criteria.categories);
      this.map.renderLegend(categories);
      this.render();
    } catch (err) {
      if (seq !== this.loadSeq) return;
      // ApiError = echec attendu ; toute autre erreur est un bug.
      if (!isApiError(err)) console.error(err);
      this.state.fail();
      this.view.showError(messageFor(err));
    } finally {
      if (seq === this.loadSeq) this.view.endLoading();
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
    this.keyword = cleanKeyword(query);
    replaceSearchParam("q", this.keyword);
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

  /** Choix manuel (clic, Entree) : invalide toute localisation en cours. */
  pick(point) {
    this.pickSeq += 1;
    // La carte laisse voir une marge autour de la zone : un clic dans cette marge garde l'ancien point.
    if (!isInCoverageArea(point)) {
      this.map.showOutOfArea(COVERAGE_AREA.name, "map");
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
      if (seq !== this.pickSeq) return;
      if (!isInCoverageArea(position)) {
        this.map.showOutOfArea(COVERAGE_AREA.name, "device");
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
