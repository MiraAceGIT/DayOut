// window.DAYOUT_CONFIG : pose par config.local.js (script classique, avant les modules).
const DEFAULT_API_BASE_URL = "http://localhost:8000";

const override = window.DAYOUT_CONFIG?.apiBaseUrl;

export const API_BASE_URL = (
  typeof override === "string" && override.trim() !== "" ? override.trim() : DEFAULT_API_BASE_URL
).replace(/\/+$/, "");

export const FRONT_ROOT = new URL("../../", import.meta.url);

export function frontUrl(path) {
  return new URL(path, FRONT_ROOT);
}
