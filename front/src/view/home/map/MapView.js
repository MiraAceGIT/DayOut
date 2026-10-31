import { LeafletMap } from "./LeafletMap.js";
import { MapLegend } from "./MapLegend.js";
import { MapStatus } from "./MapStatus.js";

export class MapView {
  constructor(root = document) {
    this.leaflet = new LeafletMap(root.querySelector("#home-map"));
    this.legend = new MapLegend(root.querySelector("#home-legend"));
    this.status = new MapStatus(root.querySelector("#home-map-status"));
  }

  get isAvailable() {
    return this.leaflet.isAvailable;
  }

  init(options) {
    if (this.leaflet.init(options) === "unavailable") this.status.unavailable();
  }

  setOrigin(point, radiusKm) {
    this.leaflet.setOrigin(point, radiusKm);
  }

  setRadius(km) {
    this.leaflet.setRadius(km);
  }

  showEvents(items, selectedId) {
    this.leaflet.showEvents(items, selectedId);
  }

  select(id, options) {
    this.leaflet.select(id, options);
  }

  renderLegend(categories) {
    this.legend.render(categories);
  }

  promptPick() {
    if (!this.isAvailable) {
      this.status.pickUnavailable();
      return;
    }
    this.leaflet.focus();
    this.status.pickHint();
  }

  clearMessage() {
    this.status.clear();
  }

  showLocating() {
    this.status.locating();
  }

  showLocated() {
    this.status.located();
  }

  showOutOfArea(areaName, from) {
    this.status.outOfArea(areaName, from);
  }

  showLocateError(code) {
    this.status.locateFailed(code);
  }
}
