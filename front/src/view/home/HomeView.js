import { loginUrl } from "../../core/routes.js";
import { LiveStatus } from "../components/liveStatus/LiveStatus.js";
import { renderSearchBox } from "../components/searchBox/searchBox.js";
import { bindTabs } from "../components/tabs/tabs.js";
import { FilterChips } from "./filters/FilterChips.js";
import { PointCard } from "./pointCard/PointCard.js";
import { ResultsList } from "./resultsList/ResultsList.js";
import { SuggestionsList } from "./suggestions/SuggestionsList.js";
import { resultsMessage } from "./homeMessages.js";

// Le compte est annonce par role=status : on attend la fin de la frappe / du glissement pour ne pas saturer.
const STATUS_DELAY_MS = 400;

/** Elements cherches dans `root`, le <main> ou le routeur a pose la page carte ; garantis par `isReady`, verifie par le controleur. */
export class HomeView {
  /** @param {ParentNode} [root] */
  constructor(root = document) {
    this.root = root;
    // Champ de recherche de l'accueil, synchronise avec celui du header par SearchBox.
    root.querySelector("#home-search-slot")?.replaceChildren(renderSearchBox({ id: "home-search" }));
    /** @type {HTMLElement[]} */
    this.tabs      = [...root.querySelectorAll('#home-tabs [role="tab"]')].map((el) => /** @type {HTMLElement} */ (el));
    this.countEl   = root.querySelector("#home-nearby-count");
    this.errorEl   = /** @type {HTMLElement} */ (root.querySelector("#home-error"));
    this.statusEl  = /** @type {HTMLElement} */ (root.querySelector("#home-status"));
    this.listEl    = /** @type {HTMLElement} */ (root.querySelector("#home-list"));
    this.loginLink = /** @type {HTMLAnchorElement|null} */ (root.querySelector("#home-login-link"));
    this.point = new PointCard({
      labelEl:   root.querySelector("#home-point-label"),
      changeBtn: root.querySelector("#home-point-change"),
      radiusEl:  root.querySelector("#home-radius"),
      radiusOut: root.querySelector("#home-radius-value"),
    });
    this.filters = new FilterChips({
      filtersEl:    root.querySelector("#home-filters"),
      categoriesEl: root.querySelector("#home-categories"),
    });
    this.results = new ResultsList(this.listEl);
    this.suggestions = new SuggestionsList({
      listEl: root.querySelector("#home-suggestions"),
      statusEl: root.querySelector("#home-suggestions-status"),
      errorEl: root.querySelector("#home-suggestions-error"),
    });
    this.status = new LiveStatus(this.statusEl);
    /** @type {(id: number) => void} */
    this.onSelect = () => {};
  }

  get isReady() {
    return Boolean(this.tabs.length && this.point.isReady && this.filters.isReady
      && this.statusEl && this.errorEl && this.listEl && this.suggestions.isReady);
  }

  /**
   * @param {{ onRadius: (km: number) => void, onFilter: (name: string, on: boolean) => void,
   *   onCategory: (slug: string, on: boolean) => void, onSelect: (id: number) => void, onChangePoint: () => void }} handlers
   * @param {AbortSignal} [signal] retire les ecouteurs et l'annonce differee au depart de la page
   */
  bind({ onRadius, onFilter, onCategory, onSelect, onChangePoint }, signal) {
    this.onSelect = onSelect;
    bindTabs(this.tabs, { root: this.root, signal });
    if (this.loginLink) this.loginLink.href = loginUrl();
    this.point.bind({ onRadius, onChangePoint }, signal);
    this.filters.bind({ onFilter, onCategory }, signal);
    signal?.addEventListener("abort", () => this.status.cancel(), { once: true });
  }

  setOrigin(point) {
    this.point.setOrigin(point);
  }

  setRadius(km) {
    this.point.setRadius(km);
  }

  renderCategoryFilters(categories, active) {
    this.filters.renderCategories(categories, active);
  }

  showLoading() {
    this.errorEl.textContent = "";
    this.results.setBusy(true);
    this.status.set("Chargement des événements…");
    this.suggestions.showLoading();
  }

  endLoading() {
    this.results.setBusy(false);
    this.suggestions.setBusy(false);
  }

  showResults(result, selectedId = null) {
    this.results.render(result.items, { selectedId, keyword: result.keyword, onSelect: (id) => this.onSelect(id) });
    if (this.countEl) this.countEl.textContent = String(result.inCircle);
    this.status.set(resultsMessage(result), { delay: STATUS_DELAY_MS });
  }

  markSelected(id, options) {
    this.results.markSelected(id, options);
  }

  showSuggestions(events) {
    this.suggestions.render(events);
  }

  showError(message) {
    this.results.clear();
    this.status.set("");
    if (this.countEl) this.countEl.textContent = "0";
    this.errorEl.textContent = message;
    this.suggestions.showError(message);
  }
}
