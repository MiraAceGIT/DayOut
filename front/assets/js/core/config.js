/**
 * Configuration unique du front.
 *
 * Valeurs par defaut ci-dessous, surchargeables en local via
 * `window.DAYOUT_CONFIG`, defini dans `assets/js/config.local.js`
 * (fichier optionnel, ignore par git, charge en script classique AVANT les
 * modules — voir `config.local.example.js`).
 */
const DEFAULT_API_BASE_URL = "http://localhost:8000";

const override = window.DAYOUT_CONFIG?.apiBaseUrl;

/** Base de l'API, sans slash final. */
export const API_BASE_URL = (
  typeof override === "string" && override.trim() !== "" ? override.trim() : DEFAULT_API_BASE_URL
).replace(/\/+$/, "");

/** Racine du front (dossier `front/`), resolue depuis ce fichier : valable quelle que soit la profondeur de la page. */
export const FRONT_ROOT = new URL("../../../", import.meta.url);

/** Construit l'URL absolue d'une page du front a partir d'un chemin relatif a `front/`. */
export function frontUrl(path) {
  return new URL(path, FRONT_ROOT);
}
