// Globales posees par des scripts classiques avant les modules (lues par core/config.js et LeafletMap.js).
interface Window {
  DAYOUT_CONFIG?: { apiBaseUrl?: string };
  /** Leaflet 1.9.4 (UMD, index.html) ; absent hors ligne ou bloque. */
  L?: any;
}
