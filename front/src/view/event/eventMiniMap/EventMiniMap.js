import { categoryClass, colorCategoryOf } from "../../components/category/category.js";
import { pinIcon } from "../../components/icon/icon.js";

const TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
// Inseree en HTML par Leaflet : attribution OSM obligatoire (licence). Aucune donnee variable n'y entre.
const ATTRIBUTION = '<a href="https://leafletjs.com">Leaflet</a> · © '
  + '<a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>';

const DEFAULT_ZOOM = 15;
const PIN_SIZE = 40;

/**
 * Carte Leaflet reduite d'un unique evenement : tuiles OSM, un marqueur, attribution.
 * Responsabilite unique : afficher la position d'un seul event ; aucune interaction metier.
 */
export class EventMiniMap {
  /** @param {HTMLElement|null} containerEl @param {typeof window.L} [L] */
  constructor(containerEl, L = window.L) {
    this.containerEl = containerEl;
    this.L = L;
    /** @type {any} */
    this.map = null;
    /** @type {any} */
    this.marker = null;
  }

  get isAvailable() {
    return Boolean(this.containerEl && this.L);
  }

  /** @param {import("../../../model/domain/event/event.js").Event} event */
  show(event) {
    if (!this.isAvailable || !event.position) return false;
    const L = /** @type {any} */ (this.L);
    const containerEl = /** @type {HTMLElement} */ (this.containerEl);
    const { lat, lng } = event.position;

    // Supprimer la carte precedente : Leaflet refuse d'initialiser deux fois sur le meme conteneur.
    this.destroy();

    const map = L.map(containerEl, { zoomControl: true, attributionControl: true, scrollWheelZoom: false });
    map.attributionControl.setPrefix(false);
    map.setView([lat, lng], DEFAULT_ZOOM);
    L.tileLayer(TILE_URL, { maxZoom: 19, noWrap: true, attribution: ATTRIBUTION }).addTo(map);

    const icon = L.divIcon({
      className: ["map-pin", categoryClass(colorCategoryOf(event))].filter(Boolean).join(" "),
      html: pinIcon({ size: PIN_SIZE }),
      iconSize: [PIN_SIZE, PIN_SIZE],
      iconAnchor: [PIN_SIZE / 2, PIN_SIZE - 2],
    });
    this.marker = L.marker([lat, lng], { icon, title: event.title, keyboard: false }).addTo(map);
    this.map = map;

    containerEl.setAttribute("role", "region");
    containerEl.setAttribute("aria-label",
      `Carte du lieu : ${event.venueName || event.city || event.title}. Utiliser + et − pour zoomer.`);
    return true;
  }

  destroy() {
    if (!this.map) return;
    // Leaflet 1.9 plante s'il finit un zoom anime sur une carte detruite (_onZoomTransitionEnd).
    this.map._animatingZoom = false;
    this.map.remove();
    this.map = null;
    this.marker = null;
  }
}
