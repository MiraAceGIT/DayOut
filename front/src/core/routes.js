// Dans core/ (et non app/boot.js) pour que les views (header, popup) puissent importer loginUrl sans cycle.

/** Chemins des ecrans ; dans l'URL, ils suivent le `#` (`#/evenements`). */
export const PATHS = Object.freeze({
  home: "/",
  events: "/evenements",
  planning: "/planning",
  faq: "/faq",
  terms: "/cgu",
  legal: "/mentions-legales",
  contact: "/contact",
  login: "/connexion",
});

export const LOGIN_PATH = PATHS.login;

/**
 * Hash vide -> accueil ; `null` si le hash n'est pas une route (`#main`, cible du skip-link).
 * @param {string} hash
 * @returns {{ path: string, params: URLSearchParams } | null}
 */
export function parseHash(hash) {
  if (hash === "" || hash === "#") return { path: PATHS.home, params: new URLSearchParams() };
  if (!hash.startsWith("#/")) return null;
  const route = hash.slice(1);
  const queryStart = route.indexOf("?");
  const path = queryStart === -1 ? route : route.slice(0, queryStart);
  const query = queryStart === -1 ? "" : route.slice(queryStart + 1);
  return { path: path.replace(/\/+$/, "") || PATHS.home, params: new URLSearchParams(query) };
}

/**
 * @param {string} path
 * @param {Record<string, string> | URLSearchParams} [params]
 */
export function routeHref(path, params) {
  const query = new URLSearchParams(params).toString();
  return `#${path}${query ? `?${query}` : ""}`;
}

function currentRoutePath() {
  const route = parseHash(location.hash) ?? parseHash("");
  // Depuis l'ecran Connexion, on garde la destination deja prevue plutot que d'imbriquer les `next`.
  if (route.path === LOGIN_PATH) return route.params.get("next") ?? PATHS.home;
  const query = route.params.toString();
  return query ? `${route.path}?${query}` : route.path;
}

/** `next` est un chemin d'ecran, mais le futur ecran Connexion devra quand meme le verifier. */
export function loginUrl(next = currentRoutePath()) {
  return routeHref(LOGIN_PATH, { next });
}

export function replaceSearchParam(name, value) {
  const url = new URL(location.href);
  if (value.trim()) url.searchParams.set(name, value);
  else url.searchParams.delete(name);
  history.replaceState(history.state, "", url);
}
