import { loginUrl } from "../../core/routes.js";
import { LiveStatus } from "../components/liveStatus/LiveStatus.js";
import { bindTabs } from "../components/tabs/tabs.js";
import { FilterChips } from "./filters/FilterChips.js";
import { PointCard } from "./pointCard/PointCard.js";
import { ResultsList } from "./resultsList/ResultsList.js";
import { SuggestionsList } from "./suggestions/SuggestionsList.js";
import { resultsMessage } from "./resultsMessage.js";

// Le compte est annonce par role=status : on attend la fin de la frappe / du glissement pour ne pas saturer.
const STATUS_DELAY_MS = 400;

export class HomeView {
  constructor(root = document) {
    this.root = root;
    this.tabs      = [...root.querySelectorAll('#home-tabs [role="tab"]')];
    this.countEl   = root.querySelector("#home-nearby-count");
    this.errorEl   = root.querySelector("#home-error");
    this.statusEl  = root.querySelector("#home-status");
    this.listEl    = root.querySelector("#home-list");
    this.loginLink = root.querySelector("#home-login-link");
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
    this.onSelect = () => {};
  }

  get isReady() {
    return Boolean(this.tabs.length && this.point.isReady && this.filters.isReady
      && this.statusEl && this.errorEl && this.listEl && this.suggestions.isReady);
  }

  bind({ onRadius, onFilter, onCategory, onSelect, onChangePoint }) {
    this.onSelect = onSelect;
    bindTabs(this.tabs, { root: this.root });
    if (this.loginLink) this.loginLink.href = loginUrl();
    this.point.bind({ onRadius, onChangePoint });
    this.filters.bind({ onFilter, onCategory });
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
    this.results.render(result.items, { selectedId, onSelect: (id) => this.onSelect(id) });
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
