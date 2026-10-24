import { setUnauthorizedHandler } from "./api.js";
import { frontUrl } from "./config.js";
import { renderHeader } from "./layout.js";

/** Page de connexion (pas encore creee). Seul endroit ou ce chemin est defini. */
export const LOGIN_PATH = "pages/login.html";

/**
 * URL de connexion avec `next` = page courante (chemin same-origin, pas d'URL absolue).
 * La future page login devra quand meme verifier que `next` reste sur le meme origin.
 */
export function loginUrl(next = location.pathname + location.search + location.hash) {
  const url = frontUrl(LOGIN_PATH);
  url.searchParams.set("next", next);
  return url.href;
}

/** A appeler en premier dans chaque script de page. */
export function boot({ page } = {}) {
  renderHeader({ current: page });

  setUnauthorizedHandler(() => {
    if (location.pathname === frontUrl(LOGIN_PATH).pathname) return; // pas de boucle sur la page login
    location.assign(loginUrl());
  });
}
