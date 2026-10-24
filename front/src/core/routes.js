import { frontUrl } from "./config.js";

// Dans core/ (et non app/boot.js) pour que les views (header, popup) puissent importer loginUrl sans cycle.

/** Page de connexion (pas encore creee). */
export const LOGIN_PATH = "pages/login.html";

/** `next` est un chemin same-origin, mais la future page login devra quand meme le verifier. */
export function loginUrl(next = location.pathname + location.search + location.hash) {
  const url = frontUrl(LOGIN_PATH);
  url.searchParams.set("next", next);
  return url.href;
}

/** Garde `?name=` a jour (lien partageable, retour arriere) sans recharger ni empiler d'historique ; vide -> retire. */
export function replaceSearchParam(name, value) {
  const url = new URL(location.href);
  if (value.trim()) url.searchParams.set(name, value);
  else url.searchParams.delete(name);
  history.replaceState(history.state, "", url);
}
