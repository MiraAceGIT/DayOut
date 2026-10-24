import { LeafletMap } from "./LeafletMap.js";
import { MapLegend } from "./MapLegend.js";
import { MapStatus } from "./MapStatus.js";

/**
 * Zone carte de la page : seul fichier qui connait ses ids. Compose la carte Leaflet, sa legende
 * et ses messages ; decide quoi dire selon que la carte est disponible ou non.
 */
export class MapView {
  constructor(root = document) {
    this.leaflet = new LeafletMap(root.querySelector("#home-map"));
    this.legend = new MapLegend(root.querySelector("#home-legend"));
    this.status = new MapStatus(root.querySelector("#home-map-status"));
  }

  get isAvailable() {
    return this.leaflet.isAvailable;
  }

  /** `handlers` : onPick({lat,lng}), onSelect(id), onLocate(). */
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

  /** "Changer" le point : invite a cliquer sur la carte (focus pour le clavier), sauf si elle est indisponible. */
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

  /** `code` : code de rejet de core/geolocation.js. */
  showLocateError(code) {
    this.status.locateFailed(code);
  }
}
