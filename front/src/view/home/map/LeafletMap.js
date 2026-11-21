import { h } from "../../../shared/dom.js";
import { categoryClass, colorCategoryOf } from "../../components/category/category.js";
import { renderEventPopup } from "../../components/event/eventPopup/eventPopup.js";
import { icon, pinIcon } from "../../components/icon/icon.js";

// Leaflet est un <script> classique (index.html) : un import ESM depuis un CDN ne peut pas porter d'attribut integrity.
const TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
// Inseree en HTML par Leaflet : aucune donnee ne doit y entrer.
const ATTRIBUTION = '<a href="https://leafletjs.com">Leaflet</a> · © '
  + '<a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>';

// Le SVG de Leaflet ne lit pas les variables CSS.
function token(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

export class LeafletMap {
  constructor(containerEl, L = window.L) {
    this.containerEl = containerEl;
    this.L = L;
    this.map = null;
    this.markers = new Map();
    this.selectedId = null;
  }

  get isAvailable() {
    return Boolean(this.L && this.map);
  }

  init({ origin, radiusKm, area, handlers, signal }) {
    if (!this.containerEl) return "absent";
    if (!this.L) {
      this.containerEl.classList.add("map-view__map--unavailable");
      return "unavailable";
    }
    const L = this.L;
    this.handlers = handlers;

    // 20 % de marge : un cercle de 20 km pose en bordure reste visible en entier.
    const bounds = L.latLngBounds([area.south, area.west], [area.north, area.east]).pad(0.2);

    // Vue initiale avant les couches : sans elle, circle.getBounds() echoue.
    this.map = L.map(this.containerEl, {
      zoomControl: false,
      attributionControl: true,
      maxBounds: bounds,
      maxBoundsViscosity: 1,
      minZoom: 9, // l'Ile-de-France entiere
    }).setView([origin.lat, origin.lng], 14);
    this.map.attributionControl.setPrefix(false);
    L.tileLayer(TILE_URL, { maxZoom: 19, noWrap: true, attribution: ATTRIBUTION }).addTo(this.map);
    L.control.zoom({ position: "topright", zoomInTitle: "Zoomer", zoomOutTitle: "Dézoomer" }).addTo(this.map);
    this.addLocateControl();

    // Sans role, l'aria-label d'une div n'est pas annonce.
    this.containerEl.setAttribute("role", "region");
    this.containerEl.setAttribute("aria-label",
      "Carte des événements. Flèches pour se déplacer, + et − pour zoomer, Entrée pour placer le point au centre.");

    this.addAreaMask(area.outline);

    const action = token("--color-action");
    this.circle = L.circle([origin.lat, origin.lng], {
      radius: radiusKm * 1000,
      color: action,
      weight: 2,
      dashArray: "8 8",
      fillColor: action,
      fillOpacity: .12,
      interactive: false,
    }).addTo(this.map);

    this.originMarker = L.marker([origin.lat, origin.lng], {
      icon: L.divIcon({
        className: "map-origin",
        html: h("span", { class: "map-origin__inner" },
          h("span", { class: "map-origin__dot" }),
          h("span", { class: "map-origin__label" }, "Point choisi")),
        iconSize: [14, 14],
        iconAnchor: [7, 7],
      }),
      interactive: false,
      keyboard: false,
    }).addTo(this.map);

    this.markerLayer = L.layerGroup().addTo(this.map);
    this.map.fitBounds(this.circle.getBounds(), { padding: [24, 24] });

    // Un clic qui ferme une bulle ne deplace pas le point : la bulle est deja fermee au moment du click.
    let popupOpenAtPress = false;
    this.map.on("mousedown", () => { popupOpenAtPress = this.hasOpenPopup(); });
    this.map.on("click", (e) => {
      if (popupOpenAtPress) {
        popupOpenAtPress = false;
        return;
      }
      handlers.onPick({ lat: e.latlng.lat, lng: e.latlng.lng });
    });
    this.containerEl.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" || e.target !== this.containerEl) return;
      const c = this.map.getCenter();
      handlers.onPick({ lat: c.lat, lng: c.lng });
    }, { signal });
    return "ready";
  }

  // Non interactif : le clic hors zone remonte a la carte, qui refuse le point.
  addAreaMask(outline) {
    const L = this.L;
    const brand = token("--color-brand");
    const world = [[-90, -180], [90, -180], [90, 180], [-90, 180]];
    L.polygon([world, outline], {
      stroke: false,
      fillColor: brand,
      fillOpacity: .35,
      interactive: false,
    }).addTo(this.map);
    L.polygon(outline, {
      color: brand,
      weight: 2,
      opacity: .7,
      fill: false,
      interactive: false,
    }).addTo(this.map);
  }

  addLocateControl() {
    const L = this.L;
    const onLocate = () => this.handlers.onLocate();
    const Locate = L.Control.extend({
      onAdd() {
        const button = h("button", {
          type: "button",
          class: "map-view__locate",
          title: "Me localiser",
          "aria-label": "Me localiser",
          onClick: onLocate,
        }, icon("locate-fixed"));
        const wrap = h("div", { class: "leaflet-bar map-view__locate-wrap" }, button);
        L.DomEvent.disableClickPropagation(wrap);
        return wrap;
      },
    });
    new Locate({ position: "topright" }).addTo(this.map);
  }

  focus() {
    this.containerEl?.focus();
  }

  destroy() {
    if (!this.map) return;
    // Leaflet 1.9 plante s'il finit un zoom anime sur une carte detruite (_onZoomTransitionEnd).
    this.map._animatingZoom = false;
    this.map.remove();
    this.map = null;
  }

  setOrigin(point, radiusKm) {
    if (!this.isAvailable) return;
    const latlng = [point.lat, point.lng];
    this.originMarker.setLatLng(latlng);
    this.circle.setLatLng(latlng);
    this.setRadius(radiusKm);
  }

  setRadius(radiusKm) {
    if (!this.isAvailable) return;
    this.circle.setRadius(radiusKm * 1000);
    this.map.fitBounds(this.circle.getBounds(), { padding: [24, 24] });
  }

  pinFor(item, selected) {
    const size = selected ? 44 : 34;
    return this.L.divIcon({
      className: ["map-pin", categoryClass(colorCategoryOf(item.event)), selected && "map-pin--selected"]
        .filter(Boolean).join(" "),
      html: pinIcon({ size }),
      iconSize: [size, size],
      iconAnchor: [size / 2, size - 2],
      popupAnchor: [0, -size + 4],
    });
  }

  hasOpenPopup() {
    for (const { marker } of this.markers.values()) if (marker.isPopupOpen()) return true;
    return false;
  }

  // clearLayers ferme la bulle ouverte : on la rouvre.
  showEvents(items, selectedId = null) {
    if (!this.isAvailable) return;
    const wasOpen = this.markers.get(String(selectedId))?.marker.isPopupOpen() ?? false;
    this.markerLayer.clearLayers();
    this.markers.clear();
    this.selectedId = null;

    for (const item of items) {
      const { event } = item;
      if (!event.position) continue;
      // Hors tabulation : la liste du panneau est le contenu accessible.
      const marker = this.L.marker([event.position.lat, event.position.lng], {
        icon: this.pinFor(item, false),
        keyboard: false,
        title: event.title,
        riseOnHover: true,
      });
      // 72 px : la bulle ne passe pas sous le bandeau d'aide.
      marker.bindPopup(() => renderEventPopup(item), {
        maxWidth: 320, autoPanPaddingTopLeft: [24, 72], autoPanPaddingBottomRight: [24, 24],
      });
      marker.on("click", () => this.handlers.onSelect(event.id));
      marker.addTo(this.markerLayer);
      this.markers.set(String(event.id), { marker, item });
    }
    if (selectedId !== null) this.select(selectedId, { openPopup: wasOpen });
  }

  select(id, { openPopup = true } = {}) {
    if (!this.isAvailable) return;
    const previous = this.markers.get(String(this.selectedId));
    if (previous) {
      previous.marker.setIcon(this.pinFor(previous.item, false));
      previous.marker.setZIndexOffset(0);
    }
    this.selectedId = id;
    const current = this.markers.get(String(id));
    if (!current) return;
    current.marker.setIcon(this.pinFor(current.item, true));
    current.marker.setZIndexOffset(1000);
    if (openPopup) current.marker.openPopup();
  }
}
