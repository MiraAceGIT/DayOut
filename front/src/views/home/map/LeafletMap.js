import { h } from "../../../lib/dom.js";
import { categoryClass, colorCategoryOf } from "../../components/category/category.js";
import { renderEventPopup } from "../../components/eventPopup/eventPopup.js";
import { icon, pinIcon } from "../../components/icon/icon.js";

// Seul fichier qui touche Leaflet. Leaflet 1.9.4 est charge en <script> classique (UMD + SRI) dans index.html :
// un import ESM depuis un CDN ne peut pas porter d'attribut integrity. Absent (hors ligne, bloque), la liste reste utilisable.
const TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png";
// Chaine statique (Leaflet l'insere en HTML) : aucune donnee ne doit y entrer.
const ATTRIBUTION = '<a href="https://leafletjs.com">Leaflet</a> · © '
  + '<a href="https://www.openstreetmap.org/copyright">contributeurs OpenStreetMap</a>';

/** Couleur d'un token CSS : le cercle Leaflet (SVG) ne lit pas les variables CSS. */
function token(name) {
  return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

/** Carte Leaflet : tuiles, controles, point choisi et son cercle, marqueurs, bulle, choix du point au clic / Entree. */
export class LeafletMap {
  constructor(containerEl, L = window.L) {
    this.containerEl = containerEl;
    this.L = L;
    this.map = null;
    this.markers = new Map(); // id -> { marker, item }
    this.selectedId = null;
  }

  get isAvailable() {
    return Boolean(this.L && this.map);
  }

  /**
   * `handlers` : onPick({lat,lng}), onSelect(id), onLocate().
   * Renvoie "ready", "unavailable" (Leaflet absent : conteneur marque) ou "absent" (pas de conteneur).
   */
  init({ origin, radiusKm, handlers }) {
    if (!this.containerEl) return "absent";
    if (!this.L) {
      this.containerEl.classList.add("map-view__map--unavailable");
      return "unavailable";
    }
    const L = this.L;
    this.handlers = handlers;

    // Vue initiale obligatoire avant d'ajouter des couches : sans elle, circle.getBounds() echoue.
    this.map = L.map(this.containerEl, { zoomControl: false, attributionControl: true })
      .setView([origin.lat, origin.lng], 14);
    this.map.attributionControl.setPrefix(false);
    L.tileLayer(TILE_URL, { maxZoom: 19, attribution: ATTRIBUTION }).addTo(this.map);
    L.control.zoom({ position: "topright", zoomInTitle: "Zoomer", zoomOutTitle: "Dézoomer" }).addTo(this.map);
    this.addLocateControl();

    // role=region : sans role, un aria-label sur une div n'est pas annonce.
    this.containerEl.setAttribute("role", "region");
    this.containerEl.setAttribute("aria-label",
      "Carte des événements. Flèches pour se déplacer, + et − pour zoomer, Entrée pour placer le point au centre.");

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

    // Un clic qui ferme une bulle (closePopupOnClick) ne doit pas aussi deplacer le point : etat lu au mousedown.
    let popupOpenAtPress = false;
    this.map.on("mousedown", () => { popupOpenAtPress = this.hasOpenPopup(); });
    this.map.on("click", (e) => {
      if (popupOpenAtPress) {
        popupOpenAtPress = false;
        return;
      }
      const p = e.latlng.wrap(); // longitude ramenee dans [-180, 180] apres un tour du monde
      handlers.onPick({ lat: p.lat, lng: p.lng });
    });
    // Alternative clavier au clic : Entree sur la carte (pas sur un controle) choisit le centre.
    this.containerEl.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" || e.target !== this.containerEl) return;
      const c = this.map.getCenter().wrap();
      handlers.onPick({ lat: c.lat, lng: c.lng });
    });
    return "ready";
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

  /**
   * `items` = [{ event, distanceKm }] ; seuls ceux qui ont une position sont dessines.
   * La bulle de l'evenement selectionne est rouverte s'il reste visible et qu'elle etait ouverte
   * (clearLayers la ferme) ; fermee par l'utilisateur, elle le reste.
   */
  showEvents(items, selectedId = null) {
    if (!this.isAvailable) return;
    const wasOpen = this.markers.get(String(selectedId))?.marker.isPopupOpen() ?? false;
    this.markerLayer.clearLayers();
    this.markers.clear();
    this.selectedId = null;

    for (const item of items) {
      const { event } = item;
      if (!event.position) continue;
      // Marqueurs hors tabulation : la liste du panneau est le contenu accessible principal.
      const marker = this.L.marker([event.position.lat, event.position.lng], {
        icon: this.pinFor(item, false),
        keyboard: false,
        title: event.title,
        riseOnHover: true,
      });
      // Marge haute de 72 px : la bulle ne passe pas sous le bandeau d'aide.
      marker.bindPopup(() => renderEventPopup(item), {
        maxWidth: 320, autoPanPaddingTopLeft: [24, 72], autoPanPaddingBottomRight: [24, 24],
      });
      marker.on("click", () => this.handlers.onSelect(event.id));
      marker.addTo(this.markerLayer);
      this.markers.set(String(event.id), { marker, item });
    }
    if (selectedId !== null) this.select(selectedId, { openPopup: wasOpen });
  }

  /** Marqueur orange + bulle ; `id` null = aucune selection. */
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
