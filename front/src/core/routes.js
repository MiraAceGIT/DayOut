import { FRONT_ROOT, frontUrl } from "./config.js";

export const LOGIN_PATH = "pages/login.html";

// TODO page login : verifier que `next` est bien un chemin de l'app avant de rediriger.
export function loginUrl(next = location.pathname + location.search + location.hash) {
  const url = frontUrl(LOGIN_PATH);
  url.searchParams.set("next", next);
  return url.href;
}

export function routeUrl(path) {
  return frontUrl(path.slice(1)).href;
}

export function currentRoute(pathname = location.pathname) {
  const base = FRONT_ROOT.pathname;
  const rest = pathname.startsWith(base) ? pathname.slice(base.length) : pathname.slice(1);
  return "/" + rest.replace(/^index\.html$/, "").replace(/\/+$/, "");
}
