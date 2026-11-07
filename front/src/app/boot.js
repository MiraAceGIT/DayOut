import { setUnauthorizedHandler } from "../core/api.js";
import { LOGIN_PATH, loginUrl, parseHash } from "../core/routes.js";
import { renderHeader } from "../view/layout/header/header.js";
import { renderFooter } from "../view/layout/footer/footer.js";
import { startRouter } from "./router.js";

/** Appele une seule fois (pages/app.js) : header et footer dessines une fois, puis le routeur. */
export function boot({ routes, notFound }) {
  const header = renderHeader();
  renderFooter();

  setUnauthorizedHandler(() => {
    if (parseHash(location.hash)?.path === LOGIN_PATH) return; // pas de boucle sur l'ecran Connexion
    location.assign(loginUrl());
  });

  startRouter({
    routes,
    notFound,
    main: document.querySelector("#main"),
    announcer: document.querySelector("#route-announcer"),
    onNavigate: (route) => header?.setCurrent(route.id),
  });
}
