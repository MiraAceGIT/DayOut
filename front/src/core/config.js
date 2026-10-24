// `window.DAYOUT_CONFIG` vient de config.local.js, charge en script classique AVANT les modules.
const DEFAULT_API_BASE_URL = "http://localhost:8000";

const override = window.DAYOUT_CONFIG?.apiBaseUrl;

/** Base de l'API, sans slash final. */
export const API_BASE_URL = (
  typeof override === "string" && override.trim() !== "" ? override.trim() : DEFAULT_API_BASE_URL
).replace(/\/+$/, "");

/** Dossier `front/`, resolu depuis ce module : valable quelle que soit la profondeur de la page. */
export const FRONT_ROOT = new URL("../../", import.meta.url);

/** `path` relatif a `front/`, pas a la page courante. */
export function frontUrl(path) {
  return new URL(path, FRONT_ROOT);
}
