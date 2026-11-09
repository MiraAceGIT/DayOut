import { FRONT_ROOT, frontUrl } from "./config.js";

// Dans core/ (et non app/boot.js) pour que les views (header, popup) puissent importer loginUrl sans cycle.

export const LOGIN_PATH = "pages/login.html";

/** `next` est un chemin same-origin, mais la future page login devra quand meme le verifier. */
export function loginUrl(next = location.pathname + location.search + location.hash) {
  const url = frontUrl(LOGIN_PATH);
  url.searchParams.set("next", next);
  return url.href;
}

/** Adresse d'une page de l'app (`/events`), sans # ni .html ; marche aussi si front/ est dans un sous-dossier. */
export function routeUrl(path) {
  return frontUrl(path.slice(1)).href;
}

/** Page demandee par l'adresse : `/events` pour .../events, `/` pour .../ ou .../index.html. */
export function currentRoute(pathname = location.pathname) {
  const base = FRONT_ROOT.pathname;
  const rest = pathname.startsWith(base) ? pathname.slice(base.length) : pathname.slice(1);
  return "/" + rest.replace(/^index\.html$/, "").replace(/\/+$/, "");
}

export function replaceSearchParam(name, value) {
  const url = new URL(location.href);
  if (value.trim()) url.searchParams.set(name, value);
  else url.searchParams.delete(name);
  history.replaceState(history.state, "", url);
}
