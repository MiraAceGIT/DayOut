# Front — DayOut (HTML / CSS / JS vanilla)

Interface statique qui consomme l'API REST du back via `fetch()`.
Pas de framework, pas de build, pas de npm : modules ES natifs servis tels quels.

## Structure

```
front/
├── index.html                    ← carte "Que faire autour de toi ?" (accueil plein ecran, sans footer)
├── pages/
│   └── events.html               ← liste des evenements (cartes + footer)
├── assets/
│   ├── img/dayout-logo-light.svg ← logo pour fond violet nuit (header, footer)
│   └── css/                      ← CSS global (sans JS proprietaire)
│       ├── main.css              ← point d'entree unique (@import de toutes les feuilles)
│       ├── reset.css             ← reset minimal + [hidden] + prefers-reduced-motion
│       ├── tokens.css            ← charte : couleurs, typo, rayons, --space-*, focus
│       ├── base.css              ← elements (body, titres Fredoka, liens orange texte, focus)
│       ├── layout.css            ← .container
│       └── components/           ← composants purement HTML/CSS
│           ├── button.css        ← .button (--primary/--secondary/--dark/--on-dark/--small), .link-button, .chip
│           ├── skip-link.css, card-grid.css
│           └── feedback.css (alertes / statut), form.css (.input, erreurs de champ)
├── src/
│   ├── config.local.example.js   ← modele de surcharge locale (a copier)
│   ├── config.local.js           ← surcharge locale optionnelle (ignore par git)
│   ├── core/
│   │   ├── config.js             ← configuration unique (API_BASE_URL, racine du front)
│   │   ├── routes.js             ← LOGIN_PATH, loginUrl(next) (liens) ; replaceSearchParam (?q=, controleurs)
│   │   ├── geolocation.js        ← currentPosition() : API navigateur, sur clic uniquement
│   │   ├── api.js                ← wrapper fetch() : enveloppe, erreurs, CSRF, 401, timeout
│   │   └── errors.js             ← messageFor(err), isApiError(err) : messages utilisateur
│   ├── app/
│   │   └── boot.js               ← boot({ page }) : header + footer + handler 401
│   ├── shared/
│   │   ├── dom.js                ← h(tag, props, ...children) : DOM sans innerHTML
│   │   ├── format.js             ← formatDate/Day/Hour, formatEventWhen, formatDistance, formatPrice,
│   │   │                            formatCoordinates, pluralize
│   │   ├── geo.js                ← distanceKm (haversine), isLatLng (calcul pur)
│   │   └── forms.js              ← showFieldErrors, clearFieldErrors
│   ├── model/                    ← M : sans DOM (donnees, acces API, regles metier, etat)
│   │   ├── dto/                  ← typedefs du JSON de l'API (snake_case), un fichier par ressource
│   │   │   ├── eventDto.js       ← EventDto, EventPageDto (GET /events)
│   │   │   └── categoryDto.js    ← CategoryDto
│   │   ├── mappers/              ← seuls a lire les noms du JSON : DTO <-> modele (camelCase)
│   │   │   ├── eventMapper.js    ← toEvent(dto), toEvents(dtos), toEventDto(event)
│   │   │   └── categoryMapper.js ← toCategory(dto), toCategoryDto(category)
│   │   ├── services/             ← appels a l'API REST, un service par ressource appelee
│   │   │   └── eventService.js   ← fetchUpcomingEvents (GET /events -> toEvents), MAX_EVENTS_LIMIT
│   │   ├── domain/               ← regles metier pures (ni reseau, ni DOM, ni etat)
│   │   │   ├── Event.js          ← remainingPlaces, availabilityOf, LAST_PLACES_THRESHOLD, isUpcoming
│   │   │   ├── category.js       ← categoriesIn (aucune notion de couleur)
│   │   │   ├── coverageArea.js   ← COVERAGE_AREA (Ile-de-France), isInCoverageArea (contour exact)
│   │   │   ├── ileDeFranceOutline.js ← contour de la region (genere, ~300 m)
│   │   │   ├── eventFilters.js   ← un predicat par critere (gratuit, places, categories, week-end, texte),
│   │   │   │                        USER_FILTERS, matchesCriteria, weekendRange
│   │   │   └── eventSearch.js    ← searchEvents : a venir + criteres + distance/rayon + tri + comptes
│   │   └── state/                ← etat d'une page (une classe par page qui en a besoin)
│   │       └── MapSearch.js      ← etat de la page carte (criteres, chargement, selection), DEFAULT_ORIGIN
│   ├── view/                     ← V : un dossier par composant, son JS + sa feuille CSS
│   │   ├── layout/header/        ← renderHeader({ current }), NAV_ITEMS
│   │   │   └── searchBox.js      ← renderSearchBox(), SearchBox (setQuery, bindLive) : seul a connaitre l'id du champ
│   │   ├── layout/footer/        ← renderFooter() dans footer[data-layout]
│   │   ├── components/
│   │   │   ├── category/         ← slug -> .cat--<cle> (categoryClass, colorCategoryOf, hasCharterColor,
│   │   │   │                        renderCategoryDot) + category.css : seul lien categorie -> couleur
│   │   │   ├── logo/             ← renderLogo() : seul endroit qui connait le fichier du logo
│   │   │   ├── icon/icon.js      ← icon(name), pinIcon() : SVG Lucide via createElementNS
│   │   │   ├── pill/             ← renderCategoryPill, renderPricePill, renderPlacesPill (+ .pill)
│   │   │   ├── eventMeta/        ← joinMeta, placeOf, distanceText (lignes de details)
│   │   │   ├── eventCard/        ← carte complete (page evenements)
│   │   │   ├── eventRow/         ← carte compacte de la liste de la carte (selection synchronisee)
│   │   │   ├── eventPopup/       ← bulle du marqueur selectionne
│   │   │   ├── tabs/             ← bindTabs(tabs) : onglets ARIA sur markup existant (+ .tabs)
│   │   │   └── liveStatus/       ← LiveStatus : region role=status, texte immediat ou differe
│   │   ├── home/
│   │   │   ├── HomeView.js       ← panneau gauche : seul a connaitre ses ids, compose les sous-vues
│   │   │   ├── resultsMessage.js ← texte du statut des resultats (pur)
│   │   │   ├── pointCard/        ← PointCard : point choisi, "Changer", curseur de rayon
│   │   │   ├── filters/          ← FilterChips : puces fixes + puces de categories
│   │   │   ├── resultsList/      ← ResultsList : liste, aria-busy, selection
│   │   │   ├── map/
│   │   │   │   ├── MapView.js    ← zone carte : ids, compose LeafletMap + MapLegend + MapStatus
│   │   │   │   ├── LeafletMap.js ← seul fichier qui touche Leaflet (carte, cercle, marqueurs, bulle)
│   │   │   │   ├── MapLegend.js  ← legende "Categories" (DOM simple)
│   │   │   │   ├── MapStatus.js  ← textes de #home-map-status (localisation, carte indisponible)
│   │   │   │   └── map.css, mapLegend.css
│   │   │   └── home.css          ← mise en page de la page carte
│   │   └── EventsView.js         ← #events-list / -error / -status
│   ├── controller/               ← C : mediateurs minces
│   │   ├── HomeController.js     ← views -> MapSearch -> rendu ; chargement, geolocalisation, ?q=
│   │   └── EventsController.js
│   └── pages/
│       ├── home.js               ← point d'entree de index.html (lit ?q=)
│       └── events.js             ← point d'entree de pages/events.html
├── .gitignore
└── README.md
```

### Architecture MVC

Un dossier par couche : `model/`, `view/`, `controller/`. `pages/`, `app/`, `core/` et `shared/` sont
transverses (points d'entree, demarrage, infrastructure, utilitaires).

- **model/** : tout ce qui est sans DOM.
  - **model/dto/** : forme exacte du JSON echange avec l'API, dans la convention du back (snake_case),
    miroir de `back/src/Model/Dto`. Typedefs JSDoc uniquement, aucun code.
  - **model/mappers/** : seuls a connaitre les noms du JSON. `toEvent(dto)` construit l'objet du front (camelCase,
    `position: {lat, lng}`, places restantes et disponibilite calculees) ; `toEventDto(event)` fait l'inverse
    pour un envoi (les champs calcules ne repartent pas). Renommer un champ cote API ne touche qu'ici.
  - **model/services/** : acces a l'API REST (appels via `core/api.js`), renvoie des modeles via les mappers.
    Equivalent cote client de l'acces base de donnees de `back/src/Model/Dao`. Un service par ressource
    appelee (`/events` -> `eventService.js`), pas par modele : les categories arrivent dans les evenements.
  - **model/domain/** : regles metier pures (a venir, places restantes, "dernieres places", filtres,
    zone couverte), un fichier par sujet. Aucun DOM, aucun texte affiche, aucune couleur, aucun nom du JSON,
    aucun appel reseau, aucun etat.
  - **model/state/** : etat d'une page (`MapSearch` : evenements charges, criteres, selection), qui
    s'appuie sur `domain/` pour calculer les resultats. Memes interdits que `domain/`.
- **view/** : construisent et mettent a jour le DOM (`h()`, `textContent`) et portent **tous** les textes
  et choix d'affichage (messages, couleurs de categorie). Aucun appel API, aucune regle metier : elles
  recoivent des objets normalises. Une view de page (`HomeView`, `MapView`, `EventsView`) est seule a
  connaitre ses ids et les passe a des sous-vues sans id (`PointCard`, `ResultsList`...), reutilisables.
  Une view ne pilote jamais le DOM d'une autre : elle passe par son API (ex. `SearchBox` du header).
  Chaque composant a son dossier, avec son JS et sa feuille CSS cote a cote.
- **controller/** : mediateurs minces : relaient les evenements des views vers le model, puis
  redessinent. Ne touchent jamais le DOM, ne contiennent ni texte affiche ni regle metier.
  Dependances injectables par le constructeur (views, `loadEvents`, `getPosition`...) avec valeurs par defaut :
  pas de conteneur d'injection.
- **pages/** : points d'entree minces, un par page HTML : `boot({ page })` **en premier**,
  puis demarrage du controleur.
- Fonctions pour ce qui est sans etat (models, composants) ; petite classe pour ce qui garde
  des references (une view et ses elements, l'etat d'une page, le controleur qui les relie).

### Regle de dependances

```
pages/           -> app/boot.js, controller/
controller/      -> model/services/, model/state/, model/domain/, view/, core/errors.js, core/geolocation.js, core/routes.js (replaceSearchParam)
model/services/  -> core/api.js, model/mappers/, model/dto/ (types)
model/mappers/   -> model/domain/ (regles de calcul), model/mappers/, model/dto/ (types)
model/state/     -> model/domain/
model/domain/    -> model/domain/, shared/geo.js (calcul pur)
view/            -> view/, shared/, core/config.js (frontUrl), core/routes.js (loginUrl uniquement)
app/boot.js      -> core/, view/layout/
core/            -> core/ uniquement
shared/          -> rien (utilitaires purs)
```

- Rien n'importe `pages/` ; pas de dependance circulaire (`loginUrl` vit dans `core/routes.js` pour que
  le header l'utilise sans importer `app/boot.js`).
- Une view n'importe ni `model/` ni `core/api.js` ; un model n'importe ni `view/` ni `shared/dom.js`.
  Ce dont une view a besoin cote metier est un champ de l'objet normalise (`event.remainingPlaces`,
  `event.availability`) ou lui est passe par le controleur (categories presentes, resultats).
- Leaflet n'est lu que dans `view/home/map/LeafletMap.js`.

## Page carte (index.html)

- Les evenements sont charges une fois (`limit=100`, plafond du back) puis filtres **cote client**
  (`model/domain/eventSearch.js`) : distance haversine au point choisi, rayon 1–20 km, puis les criteres de
  `model/domain/eventFilters.js` : "Ce week-end" (samedi 0 h -> lundi 0 h), "Gratuit", "Places disponibles",
  categories (OU), recherche du header (titre, lieu, ville ; sans accents ni casse). Ajouter un filtre =
  un predicat + une entree dans `USER_FILTERS` (+ son drapeau dans `MapSearch`, sa puce dans index.html).
- Un evenement **sans coordonnees** reste dans la liste ("position inconnue"), n'est pas dessine et ne
  compte pas dans "N evenements dans ce cercle" (le statut ajoute "· N sans position sur la carte").
- La liste est le contenu accessible principal ; la carte est un complement : marqueurs hors tabulation,
  carte focusable (fleches, +/−, **Entree** = placer le point au centre). Le statut `role="status"` annonce le compte.
- Evenements termines (fin, ou debut a defaut, passee ; `isUpcoming`) exclus cote client : l'API trie par date sans filtrer et
  plafonne a 100. Si la reponse atteint ce plafond, le statut precise "au moins 100 evenements charges, liste
  peut-etre incomplete". TODO back : parametre `from` (date) sur `GET /events` pour ne renvoyer que l'a venir.
- Les controles memorisent les criteres pendant le chargement mais la liste n'est dessinee qu'une fois les
  donnees chargees (jamais "Aucun evenement" pendant le chargement ou apres une erreur).
- Statut `role="status"` mis a jour 400 ms apres la derniere frappe / le dernier cran du curseur (la liste,
  elle, est immediate) ; la recherche est recopiee dans `?q=` (`history.replaceState`).
- Geolocalisation demandee **uniquement** au clic sur "Me localiser" ; refus ou erreur -> message dans
  `#home-map-status`.
- Recherche du header : formulaire GET vers `index.html?q=` ; sur la carte, elle filtre en direct sans rechargement.
- **Leaflet 1.9.4** en `<script>` classique UMD + `<link>` depuis unpkg, versions epinglees avec `integrity`
  (SRI) : un `import` ESM depuis un CDN ne peut pas porter d'empreinte. `LeafletMap` lit `window.L` ; s'il manque
  (hors ligne, bloque), un message s'affiche et la liste reste utilisable. Tuiles OpenStreetMap, attribution
  "Leaflet · © contributeurs OpenStreetMap" (lien de copyright exige par OSM).
- Categories : le referentiel du back ne recoupe la charte qu'en partie. `view/components/category/category.js` rattache
  `soiree`, `sport`, `conference`, `culture`, `tech` (-> Hackathon) et `forum-entreprises` (pas encore en base)
  a leur couleur ; les autres slugs (`concert`, `gaming`, `bde`, `exterieur`, `gastronomie`) sont gris
  (texte secondaire) et regroupes sous "Autres" dans la legende. Libelles affiches tels que renvoyes par l'API.
  Couleur d'un evenement = sa premiere categorie de la charte, sinon sa premiere categorie.
- TODO : onglet "Pour toi" (renvoie vers la connexion), liens "Pour toi / Mes evenements / Planning",
  filtre "Toutes les ecoles" (l'API ne renvoie pas l'ecole organisatrice), "Organise par …" dans la bulle,
  page detail ("Voir l'evenement" pointe vers la liste), inscription ("S'inscrire" passe par la connexion).

## Lancer en local

1. Demarrer le back (voir `back/README.md`) sur `http://localhost:8000`.
2. Servir le front — **ne pas ouvrir en `file://`** : les modules ES et
   `fetch()` cross-origin ont besoin d'un serveur HTTP. Depuis la racine du projet :
   ```bash
   php -S localhost:5500 -t front
   # XAMPP sous Windows : C:\xampp\php\php.exe -S localhost:5500 -t front
   ```
   Puis ouvrir `http://localhost:5500`.

   **Extension VS Code "Live Server"** : clic droit sur `index.html` → *Open with Live Server*
   (le port doit etre autorise par la config CORS du back).

## Tests et types

```bash
cd front && node --test "tests/**/*.test.js"    # Node 22, aucune dependance
```

- `tests/*.test.js` (`node:test`) : les couches sans DOM — mappers (aller-retour DTO <-> modele),
  filtres et recherche de la carte, `isUpcoming`, zone couverte, formats, `resultsMessage`.
  Donnees communes dans `tests/fixtures.js` (`eventDto()`, `event()`).
- `tests/contract.test.js` : la spec OpenAPI du back (assemblee par PHP) est la reference ; echoue
  si un champ de `Event` / `Category` manque au typedef de `src/model/dto/`, n'est pas lu par `toEvent`
  ou pas renvoye par `toEventDto`. Ignore sans PHP, sauf avec `REQUIRE_CONTRACT=1` (CI).
- Types : `jsconfig.json` active la verification des JSDoc (VS Code en direct, `tsc` en CI) sur
  `model/`, `shared/`, `core/` et les tests ; les views ne sont pas
  encore dans le perimetre. Globales des scripts classiques (`DAYOUT_CONFIG`, Leaflet) :
  `src/types/globals.d.ts`. En local, comme en CI : `front/tools/typecheck.sh` (installe TypeScript, version epinglee, dans
  `front/tools/.cache/`, ignore par git).

## Configuration

Une seule source : `src/core/config.js` (`API_BASE_URL`, defaut `http://localhost:8000`).

Pour pointer vers une autre API **sans modifier le code** :

```bash
cp front/src/config.local.example.js front/src/config.local.js
# puis editer apiBaseUrl dans config.local.js (fichier ignore par git)
```

`config.local.js` est charge en `<script>` classique avant les modules et definit
`window.DAYOUT_CONFIG = { apiBaseUrl: "..." }`. Absent, le navigateur signale
un 404 dans l'onglet Reseau, sans effet : les valeurs par defaut s'appliquent.

## Ajouter une page

```html
<!-- pages/ma-page.html -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=Fredoka:wght@600;700&display=swap">
<link rel="stylesheet" href="../assets/css/main.css">
...
<a class="skip-link" href="#main">Aller au contenu</a>
<header class="site-header" data-layout>
  <noscript><!-- nav de secours en liens relatifs --></noscript>
</header>
<main id="main" class="container" tabindex="-1">
  <h1>Titre unique de la page</h1>
  <p id="ma-page-error" class="alert alert--error" role="alert"></p>
  <p id="ma-page-status" class="status-message" role="status"></p>
</main>
<footer class="site-footer" data-layout>
  <noscript><!-- meme contenu en statique (voir pages/events.html) --></noscript>
</footer>
<script src="../src/config.local.js"></script>
<script type="module" src="../src/pages/ma-page.js"></script>
```

```js
// src/model/services/eventService.js : acces API ; le mapper transforme l'EventDto en modele
export async function fetchEvent(id, { signal } = {}) {
  return toEvent(await api.get(`/events/${encodeURIComponent(id)}`, { signal }));
}
// api.post(path, body, { signal, timeout }) ; api.put / api.patch / api.del
```

```js
// src/view/MaPageView.js : seul fichier qui connait les ids de la page
import { h } from "../shared/dom.js";

export class MaPageView {
  constructor(root = document) {
    this.errorEl  = root.querySelector("#ma-page-error");
    this.statusEl = root.querySelector("#ma-page-status");
  }

  get isReady() {
    return Boolean(this.errorEl && this.statusEl);
  }

  showEvent(event) { /* h(...) + textContent, jamais innerHTML */ }

  showMissing() {
    this.showError("Aucun événement sélectionné.");
  }

  showError(message) {
    this.statusEl.textContent = "";
    this.errorEl.textContent = message;
  }
}
```

```js
// src/controller/MaPageController.js : orchestre, ne touche pas le DOM
import { isApiError, messageFor } from "../core/errors.js";
import { fetchEvent } from "../model/services/eventService.js";
import { MaPageView } from "../view/MaPageView.js";

export class MaPageController {
  // Dependances injectables (tests, autre source) ; valeurs par defaut = l'application reelle.
  constructor({ view = new MaPageView(), loadEvent = fetchEvent } = {}) {
    this.view = view;
    this.loadEvent = loadEvent;
  }

  async start(id) {
    if (!this.view.isReady) return;
    if (!id) {
      this.view.showMissing();          // le texte ("Aucun evenement selectionne.") vit dans la view
      return;
    }
    try {
      this.view.showEvent(await this.loadEvent(id));
    } catch (err) {
      if (!isApiError(err)) console.error(err);
      this.view.showError(messageFor(err));
    }
  }
}
```

```js
// src/pages/ma-page.js : point d'entree mince
import { boot } from "../app/boot.js";
import { MaPageController } from "../controller/MaPageController.js";

boot({ page: "ma-page" });          // ajouter l'entree dans NAV_ITEMS (view/layout/header/header.js) si besoin

new MaPageController().start(new URLSearchParams(location.search).get("id"));
```

## Client API (`core/api.js`)

- Succes : renvoie directement `data` de l'enveloppe `{ data }` ; `null` sur 204.
- Echec : leve une `ApiError` avec `type` (`network`, `timeout`, `abort`, `parse`, `http`),
  `status`, `code`, `details`, `requestId` (en-tete `X-Request-Id`), `cause`,
  et le getter `isValidation` (422 + `VALIDATION_ERROR`).
- `credentials: "include"` par defaut (front et API sont deux origines ; session cookie a venir).
- `X-CSRF-Token` envoye sur POST/PUT/PATCH/DELETE si `setCsrfTokenProvider(fn)` fournit
  un jeton (par defaut aucun : le back n'a pas encore d'endpoint CSRF).
- `setUnauthorizedHandler(fn)` : appele sur tout 401 avant de lever l'erreur.
  `boot()` l'installe pour rediriger vers `pages/login.html?next=...` (`LOGIN_PATH`, `loginUrl()` dans `core/routes.js`).
- Timeout par defaut 10 s (`timeout: 0` pour le desactiver), combinable avec un `signal` appelant.

Formulaires : sur `err.isValidation`, `showFieldErrors(form, err.details)` pose
`aria-invalid` et un `<p id="<champ>-error" class="field-error">` relie par
`aria-describedby` ; `clearFieldErrors(form)` les retire (`shared/forms.js`). Afficher
`messageFor(err)` (`core/errors.js`) dans l'alerte de la page (message utilisateur, avec la
reference de requete) ; c'est le controleur qui le calcule et le passe a la view.

## Regles de securite

- **Jamais `innerHTML` (ni `outerHTML`, `insertAdjacentHTML`) avec des donnees.**
  Construire le DOM avec `h()` (`shared/dom.js`) ou `textContent`. `h()` refuse la prop
  `innerHTML` et n'accepte que des URL http(s) ou relatives pour `href` / `src`.
- **Construire les URL avec `encodeURIComponent`** pour chaque segment variable
  (`/events/${encodeURIComponent(id)}`) ou `URLSearchParams` pour la query string.

## Accessibilite

- Un seul `<h1>` par page, skip-link vers `#main`, `aria-current="page"` sur la nav.
- Messages dynamiques : elements **permanents** dans le HTML dont on change le `textContent`.
  `role="status"` pour l'information (chargement, resultat), `role="alert"` seul pour les erreurs
  (ne pas combiner avec `aria-live`). Ne pas les masquer en `display:none`.
- Liens du contenu soulignes ; focus visible sur liens, boutons et champs ;
  `prefers-reduced-motion` respecte.

## Charte graphique

Tokens dans `assets/css/tokens.css` ; repartition d'un ecran : 70 % neutres / 20 % violet nuit / 10 % orange + categories.

| Role | Token | Valeur |
| --- | --- | --- |
| Texte, titres, header/footer | `--color-text`, `--color-brand` | #241238 (violet nuit) |
| Fond d'action "sortir" (bouton primaire, curseur) | `--color-action` | #FF771C : texte dessus **toujours** violet nuit (6,5:1), jamais blanc (2,7:1), jamais en couleur de texte |
| Liens, prix, mots orange | `--color-link` | #AE4705 |
| Fond / cartes / chips | `--color-bg`, `--color-surface`, `--color-surface-2` | #F7F5FA, #FFFFFF, #EFEBF5 |
| Texte secondaire / bordure de champ | `--color-muted`, `--color-border` | #6B6280, #8C82A0 |
| Bordure de carte (hors charte) | `--color-border-light` | #E4DFEC, teinte claire de #8C82A0, decorative |
| Etats (toujours avec un mot) | `--color-success/-warning/-error/-info` | #157A50, #946000, #BF2B3B, #2F62D0 |
| Categories (pastilles, carte) | `--color-cat-*` | Soiree #8B3FD9, Sport #158F55, Conference #2F62D0, Hackathon #0E8597, Culture #D63A6E, Forum #A0700A |

- Typo (Google Fonts, `<link>` + preconnect dans chaque page) : **Fredoka** 600/700 pour les titres
  (H1 36/42, H2 28/34, H3 22/28), **Figtree** 400–700 pour le texte (16/24, detail 14/20, pastille 12 majuscules).
- Formes : boutons et pastilles en pilule, champs 8 px, cartes 14 px, bandeaux 24 px. Icones Lucide 20–24 px, `aria-hidden`.
- Focus : anneau `--focus-color` + halo `--focus-halo` ; les zones violettes (header, footer, skip-link) inversent les deux.
- Ton : tutoiement, verbes d'action sur les boutons, dates francaises ("Sam. 15 nov. · 9 h"), pas d'emoji.

### Logo

Fichier unique `assets/img/dayout-logo-light.svg` (blanc + orange, pour fond violet nuit), reference **seulement**
dans `view/components/logo/logo.js` (`renderLogo()`, alt "DayOut", 96 px de large minimum). Pour changer de
fichier ou de variante, modifier `LOGO_LIGHT` (et `LOGO_RATIO` si le viewBox change). Jamais de logo G4B dans le
header ; "Realise par G4B Digital" dans le footer uniquement.

### Footer

`view/layout/footer/footer.js` remplit `<footer class="site-footer" data-layout>` (logo, accroche, credit),
comme le header, avec un fallback `<noscript>` statique. Absent de la page carte (plein ecran). Liens FAQ / CGU /
Mentions legales / Contact a ajouter quand ces pages existeront (pas de lien casse d'ici la).

## Conventions CSS

- Valeurs via les tokens de `tokens.css` (`--color-*`, `--space-1..7`, `--font-size-*`, `--radius-*`) :
  pas de valeur en dur dans les composants. `theme-color` (meta HTML) reprend en litteral
  la valeur de `--color-brand` (#241238), a garder synchronisee.
- Pas de selecteur d'id pour le style : ids reserves au JS et a l'ARIA.
- Nommage BEM leger :
  - bloc : `.event-card`, `.site-nav`, `.card-grid`
  - element : `.event-card__meta`, `.site-header__logo` (double underscore)
  - variante : `.alert--error` (double tiret)
  - etat : `.is-*` (ex. `.is-open`) **uniquement** si aucun attribut ARIA ne l'exprime ;
    sinon styler l'attribut (`[aria-current="page"]`, `[aria-busy="true"]`, `[aria-invalid="true"]`).
- Composant rendu ou pilote par une view : sa feuille vit a cote de son JS
  (`src/view/components/eventCard/eventCard.css`, `src/view/home/pointCard/pointCard.css`) ; quand une
  view est decoupee, sa feuille l'est aussi. Composant purement HTML/CSS : feuille dans
  `assets/css/components/`. Dans les deux cas, ajoutee par `@import` dans `assets/css/main.css`.
