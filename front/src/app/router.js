import { frontUrl } from "../core/config.js";
import { currentRoute } from "../core/routes.js";
import { h } from "../shared/dom.js";
import { formNavigation, linkNavigation, withParam } from "./navigation.js";

/**
 * Ce que recoit le `mount` d'une page (app/appRoutes.js).
 * @typedef {object} PageContext
 * @property {HTMLElement} root     <main id="app-root"> ou la page est posee : les views y cherchent leurs elements
 * @property {AbortSignal} signal   annule quand on quitte la page
 * @property {URLSearchParams} params query string de l'adresse
 * @property {(name: string, value: string) => void} setParam remplace un parametre de l'adresse sans recharger
 *   la page (valeur vide = retire) ; sans effet une fois la page quittee
 */

/**
 * @typedef {object} Route
 * @property {string} [path]
 * @property {string} file
 * @property {(page: PageContext) => unknown} [mount]
 */

/**
 * Routeur : l'adresse `/events` charge pages/events.html et copie le contenu de son <main id="app-root">
 * dans celui de index.html. Header et footer ne bougent pas.
 * Seul le routeur change l'adresse : une page qui veut la modifier passe par `setParam`, pour que le
 * routeur sache toujours ce qui est affiche.
 * @param {{ routes: Route[], notFound: Route, root: HTMLElement|null, onChange: (path: string) => void }} options
 */
export function startRouter({ routes, notFound, root, onChange }) {
  if (!root) return;
  /** Adresse (route + query string) de la page affichee. */
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
    if (signal.aborted) return; // une autre page a ete demandee entre-temps
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
      root.focus(); // lecteur d'ecran : on arrive sur le nouveau contenu
    }
  }

  /** @param {URL} url */
  function navigate(url) {
    if (url.href !== location.href) history.pushState(null, "", url);
    show(false);
  }

  // Clic sur un lien de l'app (/events...) : on change l'adresse et la page sans recharger (voir navigation.js).
  document.addEventListener("click", (e) => {
    const link = e.target instanceof Element ? e.target.closest("a[href]") : null;
    if (!(link instanceof HTMLAnchorElement)) return;
    const url = linkNavigation(e, { href: link.href, target: link.target, download: link.hasAttribute("download") }, location);
    if (!url) return;
    e.preventDefault();
    navigate(url);
  });
  // Formulaire GET vers une page de l'app (recherche du header hors de la carte) : meme chose.
  // Sur la carte, la recherche est deja traitee (preventDefault) par HomeController.
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
  // Precedent / Suivant du navigateur.
  window.addEventListener("popstate", () => show(false));
  show(true);
}

/** Lance le JS de la page ; une erreur (synchrone ou dans sa promesse) est journalisee, pas perdue.
 * @param {Route} route @param {PageContext} page */
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

/** Lit le <main id="app-root"> d'une page de pages/ (document HTML complet, sans header ni footer). */
async function loadPage(file, signal) {
  try {
    const res = await fetch(frontUrl(file), { signal });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    // Nos propres fichiers statiques : leurs <script> ne sont pas executes par DOMParser.
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
