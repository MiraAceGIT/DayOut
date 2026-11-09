import { mountHome } from "../pages/home.js";
import { mountEvents } from "../pages/events.js";
import { mountEvent } from "../pages/event.js";

/**
 * Toutes les pages de l'app, au meme endroit.
 *   path  : adresse dans le navigateur
 *   file  : HTML de la page (dossier pages/), copie dans le #app-root de index.html
 *   mount : JS a lancer une fois ce HTML affiche (dossier src/pages/) ; optionnel
 *
 * Ajouter une page : creer pages/ma-page.html (+ son JS dans src/pages/ si besoin), puis ajouter une ligne ici.
 */
export const ROUTES = [
  { path: "/",       file: "pages/home.html",   mount: mountHome },  // page par defaut (adresse du site seule)
  { path: "/my-events", file: "pages/events.html", mount: mountEvents },
  { path: "/event",     file: "pages/event.html",  mount: mountEvent },   // detail : /event?id=42
];

/** Page affichee pour toute adresse absente de ROUTES (inconnue ou pas encore faite). */
export const NOT_FOUND = { file: "pages/not-found.html" };
