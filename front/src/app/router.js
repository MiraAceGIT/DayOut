import { frontUrl } from "../core/config.js";
import { currentRoute } from "../core/routes.js";
import { h } from "../shared/dom.js";

/**
 * Routeur : l'adresse `/events` charge pages/events.html et copie le contenu de son <main id="app-root">
 * dans celui de index.html. Header et footer ne bougent pas.
 * Les pages sont listees dans app/appRoutes.js ; `signal` (passe a mount) est annule quand on quitte la page.
 */
export function startRouter({ routes, notFound, root, onChange }) {
  if (!root) return;
  let shownPath = null;
  let leave = null;

  async function show(initial) {
    const path = currentRoute();
    if (path === shownPath) return; // meme page (ex. lien d'evitement #app-root)
    const route = routes.find((r) => r.path === path) ?? notFound;

    leave?.abort();
    leave = new AbortController();
    const { signal } = leave;
    shownPath = path;
    onChange(path);

    const page = await loadPage(route.file, signal);
    if (signal.aborted) return; // une autre page a ete demandee entre-temps
    root.className = page.className;
    root.replaceChildren(...page.children);
    document.title = page.title;
    route.mount?.({ signal });

    if (!initial) {
      window.scrollTo(0, 0);
      root.focus(); // lecteur d'ecran : on arrive sur le nouveau contenu
    }
  }

  // Clic sur un lien de l'app (/events...) : on change l'adresse et la page sans recharger.
  // Fichiers (.html, .css...), autre site, nouvel onglet, ancre de la page : comportement normal du navigateur.
  document.addEventListener("click", (e) => {
    const link = e.target instanceof Element ? e.target.closest("a[href]") : null;
    if (!(link instanceof HTMLAnchorElement) || e.defaultPrevented || e.button !== 0
      || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || link.target || link.hasAttribute("download")) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname.includes(".")) return;
    if (url.hash && url.pathname === location.pathname) return;
    e.preventDefault();
    if (url.href !== location.href) history.pushState(null, "", url);
    show(false);
  });
  // Precedent / Suivant du navigateur.
  window.addEventListener("popstate", () => show(false));
  show(true);
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
