import { frontUrl } from "../core/config.js";
import { currentRoute } from "../core/routes.js";
import { h } from "../shared/dom.js";
import { formNavigation, linkNavigation, withParam } from "./navigation.js";

/**
 * @typedef {object} PageContext
 * @property {HTMLElement} root
 * @property {AbortSignal} signal
 * @property {URLSearchParams} params
 * @property {(name: string, value: string) => void} setParam
 */

/**
 * @typedef {object} Route
 * @property {string} [path]
 * @property {string} file
 * @property {(page: PageContext) => unknown} [mount]
 */

/**
 * @param {{ routes: Route[], notFound: Route, root: HTMLElement|null, onChange: (path: string) => void }} options
 */
export function startRouter({ routes, notFound, root: rootOrNull, onChange }) {
  if (!rootOrNull) return;
  const root = rootOrNull;
  let shownLocation = null;
  /** @type {AbortController|null} */
  let leave = null;

  const pageLocation = () => currentRoute() + location.search;

  async function show(initial) {
    const path = currentRoute();
    if (pageLocation() === shownLocation) return; // une ancre seule ne change pas les donnees
    const route = routes.find((r) => r.path === path) ?? notFound;

    leave?.abort();
    leave = new AbortController();
    const { signal } = leave;
    shownLocation = pageLocation();
    onChange(path);

    const page = await loadPage(route.file, signal);
    if (signal.aborted) return;
    root.className = page.className;
    root.replaceChildren(...page.children);
    document.title = page.title;
    mount(route, {
      root,
      signal,
      params: new URLSearchParams(location.search),
      setParam: (name, value) => {
        if (signal.aborted) return;
        history.replaceState(history.state, "", withParam(location.href, name, value));
        shownLocation = pageLocation();
      },
    });

    if (!initial) {
      window.scrollTo(0, 0);
      root.focus(); // annonce la nouvelle page aux lecteurs d'ecran
    }
  }

  /** @param {URL} url */
  function navigate(url) {
    if (url.href !== location.href) history.pushState(null, "", url);
    show(false);
  }

  document.addEventListener("click", (e) => {
    const link = e.target instanceof Element ? e.target.closest("a[href]") : null;
    if (!(link instanceof HTMLAnchorElement)) return;
    const url = linkNavigation(e, { href: link.href, target: link.target, download: link.hasAttribute("download") }, location);
    if (!url) return;
    e.preventDefault();
    navigate(url);
  });
  document.addEventListener("submit", (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
    /** @type {[string, string][]} */
    const fields = [];
    for (const [name, value] of new FormData(form)) if (typeof value === "string") fields.push([name, value]);
    const url = formNavigation({ method: form.method, action: form.action, target: form.target }, fields, location);
    if (!url) return;
    e.preventDefault();
    navigate(url);
  });
  window.addEventListener("popstate", () => show(false));
  show(true);
}

/** @param {Route} route @param {PageContext} page */
function mount(route, page) {
  const report = (err) => {
    if (!page.signal.aborted) console.error(`Page ${route.file} : erreur au demarrage`, err);
  };
  try {
    Promise.resolve(route.mount?.(page)).catch(report);
  } catch (err) {
    report(err);
  }
}

async function loadPage(file, signal) {
  try {
    const res = await fetch(frontUrl(file), { signal });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    // DOMParser n'execute pas les <script> de la page.
    const doc = new DOMParser().parseFromString(await res.text(), "text/html");
    const main = doc.getElementById("app-root");
    if (!main) throw new Error("pas de <main id=\"app-root\">");
    return { title: doc.title, className: main.className, children: [...main.childNodes] };
  } catch (err) {
    if (!signal.aborted) console.error(`Page ${file} non chargee :`, err);
    return {
      title: "DayOut",
      className: "container",
      children: [h("p", { class: "alert alert--error", role: "alert" }, "Impossible d'afficher cette page. Réessaie plus tard.")],
    };
  }
}
