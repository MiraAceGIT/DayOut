import { setUnauthorizedHandler } from "../core/api.js";
import { frontUrl } from "../core/config.js";
import { LOGIN_PATH, loginUrl } from "../core/routes.js";
import { renderHeader } from "../view/layout/header/header.js";
import { renderFooter } from "../view/layout/footer/footer.js";
import { ROUTES, NOT_FOUND } from "./appRoutes.js";
import { startRouter } from "./router.js";

// Point d'entree (index.html) : header et footer dessines UNE fois, puis le routeur ne change que #app-root.
const setCurrent = renderHeader();
renderFooter();

setUnauthorizedHandler(() => {
  if (location.pathname === frontUrl(LOGIN_PATH).pathname) return; // pas de boucle sur la page login
  location.assign(loginUrl());
});

startRouter({
  routes: ROUTES,
  notFound: NOT_FOUND,
  root: document.getElementById("app-root"),
  onChange: setCurrent,
});
