import { frontUrl } from "./config.js";

// Dans core/ (et non app/boot.js) pour que les views (header, popup) puissent l'importer sans cycle.

/** Page de connexion (pas encore creee). */
export const LOGIN_PATH = "pages/login.html";

/** `next` est un chemin same-origin, mais la future page login devra quand meme le verifier. */
export function loginUrl(next = location.pathname + location.search + location.hash) {
  const url = frontUrl(LOGIN_PATH);
  url.searchParams.set("next", next);
  return url.href;
}
