import { setUnauthorizedHandler } from "../core/api.js";
import { frontUrl } from "../core/config.js";
import { LOGIN_PATH, loginUrl } from "../core/routes.js";
import { renderHeader } from "../views/layout/header/header.js";
import { renderFooter } from "../views/layout/footer/footer.js";

/** A appeler en premier dans chaque script de page. */
export function boot({ page } = {}) {
  renderHeader({ current: page });
  renderFooter();

  setUnauthorizedHandler(() => {
    if (location.pathname === frontUrl(LOGIN_PATH).pathname) return; // pas de boucle sur la page login
    location.assign(loginUrl());
  });
}
