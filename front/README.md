# Frontend DayOut — Documentation developpeur

## Documentations connexes

- API consommee par ce front : [../back/README.md](../back/README.md)
- Installation BDD : [../infra/README.md](../infra/README.md)
- Spec OpenAPI interactive (Swagger UI) : `http://localhost:8000/docs`

## 1) Contexte rapide

### Que fait ce frontend ?

Interface statique de **DayOut**, plateforme etudiante d'evenements inter-ecoles.
HTML / CSS / JS vanilla, consomme l'API REST du back via `fetch()`. Pas de framework,
pas de build, pas de npm : modules ES natifs servis tels quels.

### Statut actuel

**V1 publique en lecture seule** — Une seule page HTML, `index.html`, qui sert de **coquille** (header, footer)
et affiche les ecrans selon l'adresse apres `#` (voir [Coquille et routeur](#coquille-et-routeur)) :

- `#/` : carte interactive "Que faire autour de toi ?"
- `#/evenements` : liste des evenements (cartes)
- `#/planning`, `#/faq`, `#/cgu`, `#/mentions-legales`, `#/contact`, `#/connexion` : ecrans "Bientot disponible"
- toute autre adresse en `#/...` : ecran 404

L'auth (login, pages protegees), les inscriptions et l'admin sont en cours
(infrastructure deja en place : `boot()` installe un handler `401`, `loginUrl()` genere `#/connexion?next=...`).

### Perimetre exact

**Inclus** :

- Carte Leaflet (OpenStreetMap) avec marqueurs, bulle, legende
- Liste filtree cote client : rayon (1-20 km), week-end, gratuit, places disponibles, categories, recherche plein texte
- Geolocalisation navigateur (sur clic uniquement, respecte `prefers-reduced-motion`)
- Zone de couverture Ile-de-France (contour geographique exact)
- Client API avec enveloppe, timeout, gestion CSRF, redirection 401
- Composants reutilisables : header, footer, cartes, pastilles, chips, onglets ARIA
- Tests unitaires (`node:test`) + test de contrat contre la spec OpenAPI du back
- Verification des types JSDoc via `tsc` (`model/`, `shared/`, `core/`, tests)
- Accessibilite : skip-link, roles ARIA (`status`, `alert`), focus visible, contrastes WCAG AA

---

## 2) Prerequis & versions

| Outil        | Version  | Role                                              |
| ------------ | -------- | ------------------------------------------------- |
| **Navigateur moderne** | ES2022+  | Modules ES natifs, fetch, async iterator |
| **PHP**      | >= 8.2   | Uniquement pour servir les fichiers statiques (`php -S`) |
| **Node.js**  | >= 22    | Tests (`node:test`) et types (`tsc`)              |

### Dependances runtime (chargees par CDN avec SRI)

- **Leaflet 1.9.4** (UMD + CSS), epingle avec `integrity` ; tuiles **OpenStreetMap**
- **Google Fonts** : Figtree (400-700), Fredoka (600-700)

Aucune dependance npm installee localement : `node --test` fonctionne sans `node_modules`.

### Dependances dev (CI + types)

- TypeScript (uniquement pour verifier les JSDoc, installe par `tools/typecheck.sh` dans `tools/.cache/`, ignore par git)

---

## 3) Installation

### Etapes

```bash
# 1. Depuis la racine du projet
# Aucune dependance a installer pour le run : les modules ES sont servis tels quels

# 2. Config locale optionnelle (pour pointer vers une autre API)
cp front/src/config.local.example.js front/src/config.local.js
# Editer apiBaseUrl dans config.local.js (fichier ignore par git)

# 3. S'assurer que le back tourne (voir ../back/README.md)
# dans un autre terminal :
php -S localhost:8000 -t back/public

# 4. Servir le front — ne pas ouvrir en file://
php -S localhost:5500 -t front
# XAMPP sous Windows : C:\xampp\php\php.exe -S localhost:5500 -t front

# Ouvrir http://localhost:5500
```

### Alternative : Live Server (VS Code)

Clic droit sur `index.html` -> *Open with Live Server*. **Attention** : le port
doit etre autorise par `CORS_ORIGIN` dans `back/.env` (par defaut `http://localhost:5500`).

### Fichiers ignores (.gitignore)

- `src/config.local.js` (surcharge locale optionnelle)
- `tools/.cache/` (TypeScript installe par `typecheck.sh`)

---

## 4) Configuration

### Une seule source : `src/core/config.js`

```js
export const API_BASE_URL = /* http://localhost:8000 par defaut */;
```

### Surcharge locale (sans modifier le code)

```bash
cp front/src/config.local.example.js front/src/config.local.js
# puis editer apiBaseUrl dans config.local.js
```

```js
// config.local.js — charge en <script> classique avant les modules
window.DAYOUT_CONFIG = {
    apiBaseUrl: "https://api.dayout.dev"
};
```

Absent, le navigateur signale un 404 dans l'onglet Reseau, sans effet : les valeurs
par defaut s'appliquent.

### Globales declarees

Dans `src/types/globals.d.ts` (scripts classiques UMD, pour la verification JSDoc) :

- `DAYOUT_CONFIG` (config.local.js)
- `L` (Leaflet UMD)

---

## 5) Lancement en local

### Serveur PHP integre (recommande)

```bash
php -S localhost:5500 -t front
```

### Pourquoi pas `file://` ?

Les **modules ES** et `fetch()` cross-origin ont besoin d'un serveur HTTP.
Ouvrir `index.html` directement par `file://` **ne marchera pas**.

### Ecrans disponibles

Une seule page, `http://localhost:5500/` (`index.html`) ; l'ecran depend de l'adresse apres `#` :

| Adresse                | Ecran                                       |
| ---------------------- | ------------------------------------------- |
| `#/` (ou rien)         | carte "Que faire autour de toi ?"           |
| `#/evenements`         | liste des evenements                        |
| `#/planning`           | Mon planning ("Bientot disponible")         |
| `#/faq`, `#/cgu`, `#/mentions-legales`, `#/contact` | liens du footer ("Bientot disponible") |
| `#/connexion`          | Connexion ("Bientot disponible")            |
| toute autre `#/...`    | 404 "Page introuvable"                      |

Un hash qui ne commence pas par `#/` (ex. `#main`, cible du skip-link) n'est pas une route : l'ecran ne change pas.

---

## 6) Architecture du code (MVC + transverses)

### Structure

```
front/
├── index.html                    ← coquille unique : <head>, skip-link, header/main/footer, annonceur, <template> des ecrans
├── assets/
│   ├── img/dayout-logo-light.svg ← logo (blanc + orange, pour fond violet nuit)
│   └── css/                      ← CSS global (sans JS proprietaire)
│       ├── main.css              ← point d'entree unique (@import)
│       ├── reset.css             ← reset minimal + [hidden] + prefers-reduced-motion
│       ├── tokens.css            ← charte : couleurs, typo, rayons, --space-*, focus
│       ├── base.css              ← elements (body, titres Fredoka, liens, focus)
│       ├── layout.css            ← .container
│       └── components/           ← composants purement HTML/CSS (button, chip, skip-link, card-grid, feedback, form)
├── src/
│   ├── config.local.example.js   ← modele de surcharge locale
│   ├── config.local.js           ← surcharge locale optionnelle (ignore git)
│   ├── core/                     ← infrastructure : config, api, errors, geolocation, routes (PATHS, parseHash, routeHref, loginUrl)
│   ├── app/
│   │   ├── boot.js               ← header + footer (une fois), handler 401, demarrage du routeur
│   │   └── router.js             ← startRouter : hash -> route -> clone du <template> dans <main>, titre, focus, annonce
│   ├── shared/                   ← utilitaires purs sans dependance : dom (h), format, geo, forms
│   ├── model/                    ← M : sans DOM
│   │   ├── dto/                  ← typedefs JSDoc du JSON API (snake_case), un fichier par ressource
│   │   ├── mappers/              ← DTO <-> modele (camelCase), seuls a lire les noms JSON
│   │   ├── services/             ← appels API REST via core/api.js, retournent des modeles
│   │   ├── domain/               ← regles metier pures (ni reseau, ni DOM, ni etat)
│   │   └── state/                ← etat d'une page (ex. MapSearch : criteres, chargement, selection)
│   ├── view/                     ← V : construisent et mettent a jour le DOM
│   │   ├── layout/header/        ← renderHeader() -> { setCurrent(id) }, NAV_ITEMS, menu burger, SearchBox
│   │   ├── layout/footer/        ← renderFooter(), FOOTER_LINKS ("Liens utiles")
│   │   ├── components/           ← category, logo, icon, pill, eventMeta, eventCard, eventRow, eventPopup, tabs, liveStatus
│   │   ├── home/                 ← HomeView, PointCard, FilterChips, ResultsList, MapView (LeafletMap + MapLegend + MapStatus)
│   │   ├── EventsView.js
│   │   └── PlaceholderView.js    ← ecrans "Bientot disponible" (titre)
│   ├── controller/               ← C : mediateurs minces (HomeController, EventsController, PlaceholderController)
│   ├── pages/                    ← app.js (seul point d'entree) + un module par ecran (home, events, placeholders, notFound)
│   └── types/globals.d.ts        ← declarations globales (DAYOUT_CONFIG, Leaflet)
├── tests/                        ← node:test, aucune dependance npm
├── tools/typecheck.sh            ← installe TypeScript dans .cache/ et verifie les JSDoc
├── jsconfig.json                 ← verification JSDoc (VS Code + tsc en CI)
└── README.md
```

### Architecture MVC

Un dossier par couche : `model/`, `view/`, `controller/`. `pages/`, `app/`, `core/` et `shared/` sont
transverses (points d'entree, demarrage, infrastructure, utilitaires).

- **model/** : tout ce qui est sans DOM.
  - **dto/** : forme exacte du JSON echange avec l'API, convention du back (snake_case), miroir de `back/src/Model/Dto`. Typedefs JSDoc uniquement, aucun code.
  - **mappers/** : seuls a connaitre les noms du JSON. `toEvent(dto)` construit l'objet du front (camelCase, `position: {lat, lng}`, places restantes calculees). `toEventDto(event)` fait l'inverse pour un envoi.
  - **services/** : acces a l'API REST (via `core/api.js`). Equivalent client de `back/src/Model/Dao`. Un service par **ressource appelee** (`/events` -> `eventService.js`), pas par modele.
  - **domain/** : regles metier pures (a venir, places restantes, filtres, zone couverte). Aucun DOM, aucun texte affiche, aucune couleur, aucun nom du JSON, aucun appel reseau, aucun etat.
  - **state/** : etat d'une page (`MapSearch` : evenements charges, criteres, selection), s'appuie sur `domain/`.
- **view/** : construisent et mettent a jour le DOM (`h()`, `textContent`) et portent **tous** les textes et choix d'affichage. Aucun appel API, aucune regle metier. Une view de page est seule a connaitre ses ids et les passe a des sous-vues sans id, reutilisables.
- **controller/** : mediateurs minces (relaient les evenements des views vers le model, redessinent). Ne touchent **jamais** le DOM, ne contiennent ni texte affiche ni regle metier. Dependances injectables par le constructeur (tests).
- **pages/** : `app.js` est le **seul** point d'entree (charge par `index.html`) : il appelle `boot({ routes, notFound })`.
  Les autres fichiers sont des **modules d'ecran**, de la forme
  `{ id, path, title, template, mainClass, bodyClass, mount(), unmount() }` : `mount()` cree et demarre le controleur,
  `unmount()` appelle son `stop()` (requete annulee, carte detruite, ecouteurs retires) puis l'oublie.
- **app/** : `boot.js` dessine header et footer **une fois**, installe le handler 401 et demarre le routeur ;
  `router.js` ne connait aucun ecran (il recoit la liste des routes).
- **Fonctions** pour ce qui est sans etat (models, composants) ; **petite classe** pour ce qui garde des references (view et ses elements, etat de page, controleur).

### Regle de dependances

```
pages/app.js     -> app/boot.js, pages/ (modules d'ecran)
pages/<ecran>.js -> controller/, core/routes.js (PATHS)
controller/      -> model/services/, model/state/, model/domain/, view/, core/errors.js, core/geolocation.js, core/routes.js
model/services/  -> core/api.js, model/mappers/, model/dto/ (types)
model/mappers/   -> model/domain/ (regles de calcul), model/dto/ (types)
model/state/     -> model/domain/
model/domain/    -> model/domain/, shared/geo.js (calcul pur)
view/            -> view/, shared/, core/config.js (frontUrl), core/routes.js (PATHS, routeHref, loginUrl)
app/boot.js      -> core/, app/router.js, view/layout/
app/router.js    -> core/ uniquement
core/            -> core/ uniquement
shared/          -> rien (utilitaires purs)
```

- Seul `pages/app.js` importe les modules d'ecran ; rien d'autre n'importe `pages/` ; pas de dependance circulaire
- Le routeur ne connait aucun ecran : il recoit la liste des routes en parametre
- Une view n'importe **ni** `model/` **ni** `core/api.js` ; un model n'importe **ni** `view/` **ni** `shared/dom.js`
- Ce dont une view a besoin cote metier est un **champ de l'objet normalise** (`event.remainingPlaces`, `event.availability`) ou lui est **passe par le controleur**
- Leaflet n'est lu que dans `view/home/map/LeafletMap.js`

### Coquille et routeur

```
index.html (chargee une fois)
├── <header data-layout>   ← renderHeader(), une fois ; setCurrent(id) deplace aria-current
├── <main id="main">       ← le routeur y clone le <template id="screen-..."> de l'ecran
├── <footer data-layout>   ← renderFooter(), une fois
├── <p id="route-announcer" role="status">  ← titre de l'ecran annonce apres chaque navigation
└── <template id="screen-home|screen-events|screen-placeholder|screen-not-found">
```

A chaque `hashchange`, `startRouter` (`app/router.js`) :

1. lit `location.hash` via `parseHash` (vide = `/` ; un hash hors `#/...` est ignore) ;
2. trouve la route (sinon `notFound`) ; rien ne se passe si c'est deja l'ecran affiche ;
3. appelle `unmount()` de l'ecran precedent ;
4. clone le template dans `<main>` (`template.content.cloneNode(true)`, jamais d'`innerHTML`) ;
5. applique `mainClass` sur `<main>` et `bodyClass` sur `<body>` (carte : `map-layout` + `page-map` ; autres : `container`) ;
6. appelle `mount()` ;
7. met `document.title` a `"DayOut — " + titre` et, sauf au premier chargement : remonte en haut, met le focus
   sur le `<h1>` (`tabindex="-1"`) et ecrit le titre dans l'annonceur ;
8. appelle `onNavigate(route)` (le header deplace `aria-current`, met a jour le `next` de Connexion et ferme le menu).

Les liens internes s'ecrivent avec `routeHref(PATHS.x)` (`core/routes.js`), jamais en dur.
La recherche du header reste un formulaire GET vers `index.html?q=` : `?q=` est lu dans `location.search`.

---

## 7) Pages & composants

### Ecran carte (`#/`)

- Les evenements sont charges **une fois** (`limit=100`, plafond du back) puis filtres **cote client** (`model/domain/eventSearch.js`) :
  - distance haversine au point choisi
  - rayon 1-20 km
  - criteres de `model/domain/eventFilters.js` : "Ce week-end" (samedi 0 h -> lundi 0 h), "Gratuit", "Places disponibles", categories (OU), recherche du header (titre, lieu, ville ; sans accents ni casse)
- Un evenement **sans coordonnees** reste dans la liste ("position inconnue"), n'est pas dessine et ne compte pas dans "N evenements dans ce cercle" (le statut ajoute "· N sans position sur la carte")
- La **liste** est le contenu accessible principal ; la carte est un complement : marqueurs **hors tabulation**, carte focusable (fleches, +/−, **Entree** = placer le point au centre). Le statut `role="status"` annonce le compte.
- Evenements termines exclus cote client (`isUpcoming` : fin ou debut passe). Si la reponse atteint le plafond de 100, le statut precise "au moins 100 evenements charges, liste peut-etre incomplete".
- Les controles memorisent les criteres **pendant** le chargement mais la liste n'est dessinee qu'une fois les donnees chargees (jamais "Aucun evenement" pendant le chargement ou apres une erreur).
- Statut `role="status"` mis a jour 400 ms apres la derniere frappe / le dernier cran du curseur (la liste, elle, est immediate) ; la recherche est recopiee dans `?q=` (`history.replaceState`).
- **Geolocalisation** demandee **uniquement** au clic sur "Me localiser" ; refus ou erreur -> message dans `#home-map-status`.
- Recherche du header : formulaire GET vers `index.html?q=` ; sur la carte, elle filtre en direct sans rechargement.
- **Leaflet 1.9.4** en `<script>` classique UMD + `<link>` CSS depuis unpkg, versions epinglees avec `integrity` (SRI). `LeafletMap` lit `window.L` ; s'il manque (hors ligne, bloque), un message s'affiche et la liste reste utilisable.
- **Categories** : `view/components/category/category.js` rattache les slugs charte (`soiree`, `sport`, `conference`, `culture`, `tech`, `forum-entreprises`) a leur couleur ; les autres slugs (`concert`, `gaming`, `bde`, `exterieur`, `gastronomie`) sont gris et regroupes sous "Autres" dans la legende. Couleur d'un evenement = sa premiere categorie de la charte, sinon sa premiere categorie.

- La carte occupe toute la hauteur visible sous le header (`calc(100dvh - var(--header-min-height))`) ; le footer est
  en dessous et apparait en faisant defiler.

### Ecran liste (`#/evenements`)

- Cartes complete par evenement (image, titre, date formatee, lieu, prix, places, categories)
- Pas de filtres ni de carte

### Ecrans "Bientot disponible" et 404

- `pages/placeholders.js` : Mon planning, FAQ, CGU, Mentions legales, Contact, Connexion. Template `screen-placeholder` ;
  `PlaceholderController` + `PlaceholderView` y ecrivent le titre de l'ecran.
- `pages/notFound.js` : template `screen-not-found` ("Page introuvable"), sans controleur.

### Composants reutilisables (`src/view/components/`)

| Composant     | Role                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `category`    | slug -> classe CSS, couleur, pastille ; seul lien categorie -> couleur |
| `logo`        | `renderLogo()`, seul endroit qui connait le fichier du logo         |
| `icon`        | `icon(name)` (`search`, `map-pin`, `locate-fixed`, `menu`, `x`), `pinIcon()` : SVG Lucide via `createElementNS` |
| `pill`        | `renderCategoryPill`, `renderPricePill`, `renderPlacesPill` (+ `.pill`) |
| `eventMeta`   | `joinMeta`, `placeOf`, `distanceText` (lignes de details)            |
| `eventCard`   | carte complete (page evenements)                                     |
| `eventRow`    | carte compacte de la liste de la carte (selection synchronisee)      |
| `eventPopup`  | bulle du marqueur selectionne                                        |
| `tabs`        | `bindTabs(tabs)` : onglets ARIA sur markup existant (+ `.tabs`)      |
| `liveStatus`  | `LiveStatus` : region `role=status`, texte immediat ou differe       |

---

## 8) Modeles & regles metier (`src/model/domain/`)

### Fichiers

- **`Event.js`** : `remainingPlaces`, `availabilityOf`, `LAST_PLACES_THRESHOLD`, `isUpcoming`
- **`category.js`** : `categoriesIn` (aucune notion de couleur, que des slugs)
- **`coverageArea.js`** : `COVERAGE_AREA` (Ile-de-France), `isInCoverageArea` (contour exact)
- **`ileDeFranceOutline.js`** : contour de la region (genere, resolution ~300 m)
- **`eventFilters.js`** : un predicat par critere (gratuit, places, categories, week-end, texte), `USER_FILTERS`, `matchesCriteria`, `weekendRange`
- **`eventSearch.js`** : `searchEvents` — a venir + criteres + distance/rayon + tri + comptes

### Regle d'or

Un fichier de `domain/` ne contient :

- Aucun DOM (`document`, `h()`, `textContent`)
- Aucun **texte affiche** (les messages vivent dans les views)
- Aucune **couleur** (les couleurs vivent dans `view/components/category/`)
- Aucun **nom du JSON** (snake_case) : seuls les mappers y touchent
- Aucun **appel reseau**
- Aucun **etat** (les classes sont dans `state/`)

### Exemple : places restantes

```js
// model/domain/Event.js
export const LAST_PLACES_THRESHOLD = 10;

export function remainingPlaces(event) {
    if (!event.capacity) return null;
    return Math.max(0, event.capacity - event.registeredCount);
}

export function availabilityOf(event) {
    const left = remainingPlaces(event);
    if (left === null) return "unknown";
    if (left === 0) return "full";
    if (left <= LAST_PLACES_THRESHOLD) return "last";
    return "available";
}
```

Les views lisent `event.availability` pour choisir leur rendu (`"Complet"`, `"Dernieres places"`...).

---

## 9) DTO & types

### DTO : typedefs JSDoc

Miroir de `back/src/Model/Dto` cote back, dans la convention du back (snake_case).
**Typedefs JSDoc uniquement**, aucun code execute.

```js
// src/model/dto/eventDto.js
/**
 * @typedef {Object} EventDto
 * @property {number} id
 * @property {string} title
 * @property {string} slug
 * @property {?string} cover_image_url
 * @property {string} start_at              - ISO 8601 UTC
 * @property {string} end_at                - ISO 8601 UTC
 * @property {?number} latitude
 * @property {?number} longitude
 * @property {number} capacity
 * @property {number} registered_count
 * @property {number} price_cents
 * @property {boolean} is_paid
 * @property {boolean} school_restricted
 * @property {CategoryDto[]} categories
 */

/**
 * @typedef {Object} EventPageDto
 * @property {EventDto[]} items
 * @property {number} limit
 * @property {number} offset
 */
```

### Modele cote front

```js
/**
 * @typedef {Object} Event
 * @property {number} id
 * @property {string} title
 * @property {?string} coverImageUrl
 * @property {Date} startAt
 * @property {Date} endAt
 * @property {?{ lat: number, lng: number }} position
 * @property {number} capacity
 * @property {number} registeredCount
 * @property {?number} remainingPlaces      - calcule (domain)
 * @property {"available" | "last" | "full" | "unknown"} availability  - calcule (domain)
 * @property {boolean} isPaid
 * @property {Category[]} categories
 */
```

### Verification des types

```bash
# En local comme en CI :
front/tools/typecheck.sh
```

- `jsconfig.json` active la verification JSDoc (VS Code en direct, `tsc` en CI)
- Perimetre : `model/`, `shared/`, `core/` et les tests
- Les views **ne sont pas encore** dans le perimetre
- `tools/typecheck.sh` installe TypeScript (version epinglee) dans `tools/.cache/` (ignore par git)

---

## 10) Mappers

### Localisation

- [`src/model/mappers/eventMapper.js`](src/model/mappers/eventMapper.js) : `toEvent(dto)`, `toEvents(dtos)`, `toEventDto(event)`
- [`src/model/mappers/categoryMapper.js`](src/model/mappers/categoryMapper.js) : `toCategory(dto)`, `toCategoryDto(category)`

### Operations

```js
import { toEvent, toEvents, toEventDto } from "./eventMapper.js";

const event = toEvent(dto);          // snake_case -> camelCase + calculs domain
const events = toEvents(page.items); // liste
const dto = toEventDto(event);       // camelCase -> snake_case pour un envoi (les champs calcules ne repartent pas)
```

### Regles

- Les mappers sont **les seuls** a connaitre les noms du JSON
- Renommer un champ cote API ne touche qu'ici (+ le typedef DTO + le schema YAML cote back)
- `toEvent` construit la structure camelCase du front, incluant :
  - `position: { lat, lng } | null` (plutot que `latitude`/`longitude` separes)
  - `remainingPlaces` et `availability` calcules via `domain/Event.js`
  - Dates `Date` (plutot que strings ISO)
- `toEventDto` **ne renvoie pas** les champs calcules (pas d'aller-retour)

Le test de contrat `tests/contract.test.js` echoue si un champ du JSON back n'est pas lu par `toEvent` ou pas renvoye par `toEventDto`.

---

## 11) Services (acces API REST)

### Localisation

Fichiers dans [`src/model/services/`](src/model/services/) :

- [`eventService.js`](src/model/services/eventService.js) : `fetchUpcomingEvents`, `MAX_EVENTS_LIMIT`

### Regle

**Un service par ressource appelee** (`/events` -> `eventService.js`), **pas par modele** :
les categories arrivent dans les evenements, donc pas de `categoryService` pour l'instant.

### Exemple

```js
// src/model/services/eventService.js
import { api } from "../../core/api.js";
import { toEvents } from "../mappers/eventMapper.js";

export const MAX_EVENTS_LIMIT = 100;

export async function fetchUpcomingEvents({ from, limit = MAX_EVENTS_LIMIT, signal } = {}) {
    const params = new URLSearchParams();
    if (from) params.set("from", from.toISOString());
    params.set("limit", String(limit));
    const page = await api.get(`/events?${params.toString()}`, { signal });
    return toEvents(page.items);
}
```

### Client API (`core/api.js`)

```js
api.get(path, { signal, timeout })                   // -> data | null (204)
api.post(path, body, { signal, timeout })
api.put(path, body, { signal, timeout })
api.patch(path, body, { signal, timeout })
api.del(path, { signal, timeout })
```

- **Succes** : renvoie directement `data` de l'enveloppe `{ data }` ; `null` sur 204.
- **Echec** : leve une `ApiError` avec `type` (`network`, `timeout`, `abort`, `parse`, `http`), `status`, `code`, `details`, `requestId` (en-tete `X-Request-Id`), `cause`, et le getter `isValidation` (422 + `VALIDATION_ERROR`).
- `credentials: "include"` par defaut (front et API sont deux origines ; session cookie a venir).
- `X-CSRF-Token` envoye sur POST/PUT/PATCH/DELETE si `setCsrfTokenProvider(fn)` fournit un jeton.
- `setUnauthorizedHandler(fn)` : appele sur tout 401 avant de lever l'erreur. `boot()` l'installe pour envoyer vers `#/connexion?next=...` (`loginUrl()` dans `core/routes.js`).
- **Timeout par defaut 10 s** (`timeout: 0` pour desactiver), combinable avec un `signal` appelant.

---

## 12) Controleurs (mediateurs minces)

### Localisation

- [`src/controller/HomeController.js`](src/controller/HomeController.js) : ecran carte
- [`src/controller/EventsController.js`](src/controller/EventsController.js) : ecran liste
- [`src/controller/PlaceholderController.js`](src/controller/PlaceholderController.js) : ecrans "Bientot disponible"

### Regles

- **Ne touchent jamais le DOM** (les views s'en chargent)
- **Ne contiennent ni texte affiche ni regle metier** (les views et `domain/` s'en chargent)
- Dependances **injectables par le constructeur** (views, `loadEvents`, `getPosition`...) avec valeurs par defaut
- Pas de conteneur d'injection : le constructeur suffit
- Un controleur qui charge des donnees ou pose des ecouteurs hors de `<main>` a une methode **`stop()`**, appelee par
  `unmount()` de son module d'ecran : `AbortController` sur la requete (une erreur `abort` n'est jamais affichee),
  resultats tardifs ignores (geolocalisation comprise), carte detruite (`MapView.destroy()`), recherche du header
  debranchee (`SearchBox.bindLive()` renvoie la fonction de desabonnement)

### Exemple

```js
// src/controller/EventsController.js
import { isApiError, messageFor } from "../core/errors.js";
import { fetchUpcomingEvents } from "../model/services/eventService.js";
import { EventsView } from "../view/EventsView.js";

export class EventsController {
    constructor({ view = new EventsView(), loadEvents = fetchUpcomingEvents } = {}) {
        this.view = view;
        this.loadEvents = loadEvents;
    }

    async start() {
        if (!this.view.isReady) return;
        try {
            this.view.showLoading();
            const events = await this.loadEvents();
            this.view.showEvents(events);
        } catch (err) {
            if (!isApiError(err)) console.error(err);
            this.view.showError(messageFor(err));
        }
    }
}
```

### Module d'ecran

```js
// src/pages/events.js
import { PATHS } from "../core/routes.js";
import { EventsController } from "../controller/EventsController.js";

let controller = null;

export const events = {
  id: "events", path: PATHS.events, title: "Événements",
  template: "screen-events", mainClass: "container",
  mount() {
    controller = new EventsController();
    controller.start();
  },
  unmount() {
    controller?.stop();
    controller = null;
  },
};
```

---

## 13) Gestion des erreurs

### Erreurs d'API — `ApiError`

Levee par `core/api.js` ; les controleurs la rattrapent et affichent via la view.

```js
try {
    const events = await fetchUpcomingEvents();
} catch (err) {
    if (err.isValidation) {
        // 422 avec details = champ -> message
        showFieldErrors(form, err.details);
    } else {
        console.error(err);
        this.view.showError(messageFor(err));
    }
}
```

| Champ       | Type                                                | Description                           |
| ----------- | --------------------------------------------------- | ------------------------------------- |
| `type`      | `network` / `timeout` / `abort` / `parse` / `http`  | Nature de l'erreur                    |
| `status`    | `number \| null`                                    | Code HTTP (si `http`)                 |
| `code`      | `string \| null`                                    | Code d'erreur applicatif (back)       |
| `details`   | `object \| null`                                    | Details structures (ex. erreurs par champ pour 422) |
| `requestId` | `string \| null`                                    | `X-Request-Id` du back                |
| `cause`     | `Error \| null`                                     | Erreur sous-jacente                   |
| `isValidation` (getter) | `boolean`                               | `status === 422 && code === "VALIDATION_ERROR"` |

### Messages utilisateur — `core/errors.js`

- `messageFor(err)` : construit un message utilisateur a afficher (avec la reference de requete si disponible)
- `isApiError(err)` : type guard

C'est le **controleur** qui calcule le message et le passe a la view (la view garde le controle du texte final mais accepte un parametre).

### Erreurs de formulaire

```js
import { showFieldErrors, clearFieldErrors } from "../shared/forms.js";

form.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearFieldErrors(form);
    try {
        await api.post("/events", data);
    } catch (err) {
        if (err.isValidation) {
            showFieldErrors(form, err.details);    // aria-invalid + <p class="field-error">
            return;
        }
        this.view.showError(messageFor(err));
    }
});
```

`showFieldErrors` pose `aria-invalid="true"` sur le champ et un `<p id="<champ>-error" class="field-error">` relie par `aria-describedby`. `clearFieldErrors` les retire.

### Redirection 401

`boot()` installe un handler `setUnauthorizedHandler` qui envoie vers
`#/connexion?next=<ecran courant>` sur tout 401 (sans boucler si l'ecran Connexion est deja affiche). Les appels API echouent **avant** d'atteindre le controleur sur 401 -> l'utilisateur est redirige directement.

### Erreurs reseau et offline

- `type: "network"` : navigateur offline ou CORS bloque
- `type: "timeout"` : depasse les 10 s par defaut
- `type: "abort"` : `AbortController.abort()` appele par l'appelant
- `type: "parse"` : reponse non-JSON ou JSON malforme

Pour Leaflet absent (hors ligne, bloque) : `MapStatus` affiche "Carte indisponible" et la liste reste utilisable.

---

## 14) API consommee

Voir [`../back/README.md`](../back/README.md) pour la liste complete.

### Endpoints utilises

| Methode | Chemin         | Utilise par                   |
| ------- | -------------- | ----------------------------- |
| GET     | `/events`      | `fetchUpcomingEvents` (home, events) |
| GET     | `/events/{id}` | (page detail a venir)         |
| GET     | `/health`      | (non utilise)                 |

### Conventions

- Requetes **avec cookies** (`credentials: "include"`) : auth future par session
- En-tete `X-CSRF-Token` sur POST/PUT/PATCH/DELETE (via `setCsrfTokenProvider`, pas encore cable)
- En-tete de reponse `X-Request-Id` utilise pour les messages d'erreur (traque cote back)
- CORS : l'origine du front doit etre dans `CORS_ORIGIN` du `.env` back (defaut `http://localhost:5500`)

---

## 15) Tests

### Runner — `node:test` (Node 22)

```bash
cd front
node --test "tests/**/*.test.js"
```

**Aucune dependance npm** installee. Tout est standard Node 22.

### Arborescence

```
tests/
├── eventMapper.test.js      ← aller-retour DTO <-> modele
├── eventSearch.test.js      ← filtres, recherche, distance, tri
├── shared.test.js           ← dom (h), format (dates/prix/distance), geo (haversine), forms
├── contract.test.js         ← contrat avec la spec OpenAPI du back
├── routes.test.js           ← parseHash, routeHref, loginUrl (adresses #/...)
└── fixtures.js              ← eventDto(), event() : donnees communes
```

### Perimetre

- **Mappers** : `toEvent`, `toEventDto`, aller-retour bidirectionnel
- **Domain** : filtres, recherche de la carte, `isUpcoming`, zone couverte, places restantes
- **Shared** : `h()` (XSS, URLs refusees), formats (dates, prix, distance, pluralize), `distanceKm`, `isLatLng`
- **Views** : pas encore couvertes (peu de logique testable, le DOM est teste indirectement)

### Test de contrat (`contract.test.js`)

- La spec OpenAPI du back (assemblee par PHP) est la **reference**
- Echoue si un champ de `Event` / `Category` **manque** au typedef de `src/model/dto/`, **n'est pas lu** par `toEvent` ou **pas renvoye** par `toEventDto`
- **Ignore sans PHP**, sauf avec `REQUIRE_CONTRACT=1` (force l'echec si PHP manque — utilise en CI)

### Test manuel navigateurs (Chrome, Firefox, Edge)

A faire dans chacun des trois navigateurs, en largeur ordinateur (1440 px) puis mobile (360 px, mode responsive des DevTools) :

- [ ] `/` affiche la carte ; la carte remplit l'ecran sous le header ; le footer apparait en faisant defiler
- [ ] Carte -> Evenements -> Mon planning sans rechargement (onglet Reseau : pas de nouveau document) ; titre de l'onglet a jour
- [ ] Precedent / Suivant du navigateur ; `#/xyz` affiche "Page introuvable"
- [ ] Skip-link (Tab des l'arrivee, Entree) : focus dans le contenu, ecran inchange
- [ ] Apres un clic de menu : focus sur le titre de l'ecran, annonce par le lecteur d'ecran (NVDA / Narrateur)
- [ ] Lien actif souligne en orange ; footer : FAQ, CGU, Mentions legales, Contact menent aux ecrans "Bientot disponible"
- [ ] 10 allers-retours Carte <-> Evenements : aucune erreur console (hors 404 de `config.local.js`), la carte marche, une frappe dans la recherche = un seul filtrage
- [ ] Mobile : burger au clavier (Tab jusqu'au bouton, Entree ouvre, Tab parcourt les liens, Echap ferme et rend le focus au bouton),
      au doigt (clic sur un lien ferme, clic a l'exterieur ferme) ; elargir au-dela de 900 px ferme le panneau
- [ ] Pas de defilement horizontal de 360 a 1440 px ; zoom navigateur 200 % utilisable

### Ajouter un test

```js
// tests/eventFilters.test.js
import { test } from "node:test";
import assert from "node:assert/strict";
import { event } from "./fixtures.js";
import { matchesCriteria } from "../src/model/domain/eventFilters.js";

test("week-end filter includes Saturday 23h59", () => {
    const e = event({ startAt: new Date("2026-11-07T23:59:00Z") });
    assert.equal(matchesCriteria(e, { weekend: true }), true);
});
```

---

## 16) Qualite & style

### Types JSDoc

```bash
front/tools/typecheck.sh
```

- Installe TypeScript (version epinglee) dans `tools/.cache/` au premier lancement
- Verifie les JSDoc de `model/`, `shared/`, `core/`, tests
- Globales (`DAYOUT_CONFIG`, `L`) declarees dans `src/types/globals.d.ts`

### Conventions de nommage

- **Variables / fonctions** : `camelCase`
- **Classes** : `PascalCase`
- **Constantes de module** : `UPPER_SNAKE_CASE` (`MAX_EVENTS_LIMIT`, `LAST_PLACES_THRESHOLD`)
- **Fichiers** : camelCase sauf pour les classes exportees par defaut (ex. `EventsController.js`, `EventsView.js`)

### Conventions de langue

- Code en anglais (noms de fonctions, variables, classes, fichiers)
- **Textes affiches** et commentaires en **francais**
- Les textes affiches vivent **uniquement** dans les views

### Regles CSS

- Valeurs via les **tokens** de `tokens.css` (`--color-*`, `--space-1..7`, `--font-size-*`, `--radius-*`) : pas de valeur en dur dans les composants
- **Aucune couleur en dur hors de `tokens.css`** (ombres comprises : `--shadow`, `--shadow-popup`, `--shadow-pin`). Seule exception :
  `theme-color` (meta HTML), qui reprend en litteral la valeur de `--color-brand` (`#241238`), a **garder synchronisee**
- Une couleur dont un attribut SVG a besoin passe par une classe stylee en CSS (ex. `.map-pin__outline`), pas par l'attribut
- **Pas de selecteur d'id** pour le style : ids reserves au JS et a l'ARIA
- **Nommage BEM leger** :
  - bloc : `.event-card`, `.site-nav`, `.card-grid`
  - element : `.event-card__meta`, `.site-header__logo` (double underscore)
  - variante : `.alert--error` (double tiret)
  - etat : `.is-*` **uniquement** si aucun attribut ARIA ne l'exprime ; sinon styler l'attribut (`[aria-current="page"]`, `[aria-busy="true"]`, `[aria-invalid="true"]`)
- **Composant rendu par une view** : feuille a cote de son JS (`src/view/components/eventCard/eventCard.css`, `src/view/home/pointCard/pointCard.css`) ; quand une view est decoupee, sa feuille l'est aussi
- **Composant purement HTML/CSS** : feuille dans `assets/css/components/`
- Dans les deux cas, ajoutee par `@import` dans `assets/css/main.css`

---

## 17) Securite & accessibilite

### Securite

- **Jamais `innerHTML` (ni `outerHTML`, `insertAdjacentHTML`) avec des donnees.** Construire le DOM avec `h()` (`shared/dom.js`) ou `textContent`. `h()` refuse la prop `innerHTML` et n'accepte que des URL http(s) ou relatives pour `href` / `src`.
- **Construire les URL avec `encodeURIComponent`** pour chaque segment variable (`/events/${encodeURIComponent(id)}`) ou `URLSearchParams` pour la query string.
- **Leaflet** charge via UMD + CSS epingles avec **SRI** (`integrity="sha384-..."`) : un `import` ESM depuis un CDN ne peut pas porter d'empreinte.
- **Google Fonts** : chargees avec `<link rel="preconnect">` + `crossorigin` ; pas de `@import` dans le CSS (plus lent et moins securise).
- **CSP** : le back pose `default-src 'none'` sur toutes les reponses API ; `/docs` a la sienne. Le front charge depuis unpkg et Google Fonts, listes dans le `<link>` et le `<script src>` avec SRI.
- **Jamais de secret cote client** (l'API utilise des sessions cookies httpOnly ou JWT avec expiration courte).

### Accessibilite

- Un seul `<h1>` par **ecran** (dans son `<template>`)
- **Skip-link** vers `#main` (lien visible au focus) ; `#main` n'est pas une route, l'ecran ne change pas
- **Changement d'ecran** (sauf premier chargement) : retour en haut, focus sur le `<h1>` (`tabindex="-1"`),
  titre ecrit dans l'annonceur permanent `#route-announcer` (`role="status"`) ; `document.title` = `"DayOut — " + titre`
- `aria-current="page"` sur le lien de l'ecran courant (header), deplace par `setCurrent(id)`
- **Menu burger** (< 900 px) : vrai `<button>` avec `aria-expanded`, `aria-controls="site-menu"` et `aria-label`
  ("Ouvrir le menu" / "Fermer le menu"), zone d'au moins `--tap-target` ; le panneau se ferme sur Echap (focus rendu
  au bouton), clic sur un lien, clic exterieur, tabulation hors du panneau et passage au-dessus de 900 px
- Footer : liens dans `<nav aria-label="Liens utiles">`
- **Messages dynamiques** : elements **permanents** dans le HTML dont on change le `textContent`.
  - `role="status"` pour l'information (chargement, resultat)
  - `role="alert"` seul pour les erreurs (ne pas combiner avec `aria-live`)
  - Ne pas les masquer en `display:none`
- **Liens** du contenu soulignes ; **focus visible** sur liens, boutons et champs
- **`prefers-reduced-motion`** respecte (reset.css coupe les transitions)
- **Carte** : marqueurs hors tabulation, carte focusable (fleches, +/-, Entree)
- **Contrastes WCAG AA** : `#FF771C` (bouton primaire) + texte violet nuit (`#241238`) = 6.5:1 ; jamais blanc sur orange (2.7:1)

---

## 18) Commandes utiles (dev)

### Servir le front

```bash
php -S localhost:5500 -t front
# XAMPP sous Windows : C:\xampp\php\php.exe -S localhost:5500 -t front
```

### Tests

```bash
cd front
node --test "tests/**/*.test.js"                  # tout
node --test tests/eventMapper.test.js             # un fichier
REQUIRE_CONTRACT=1 node --test tests/contract.test.js   # force l'echec si PHP manque
```

### Types JSDoc

```bash
front/tools/typecheck.sh
```

### Debug client API

Dans la console navigateur :

```js
// Voir le requestId d'une erreur
try { await fetch("/events").then(r => r.json()) } catch (e) { console.log(e.requestId); }
```

Les logs serveur sont dans `back/storage/logs/` (voir `../back/README.md`).

---

## 19) Deploiement (apercu frontend)

### Methode cible

Hebergement statique (nginx, Apache, Cloudflare Pages, Netlify, Vercel...).
Aucun build, aucun bundler : copier le contenu de `front/` tel quel.

### Configuration en prod

1. Copier `src/config.local.example.js` -> `config.local.js` avec l'URL de l'API prod :
   ```js
   window.DAYOUT_CONFIG = {
       apiBaseUrl: "https://api.dayout.example"
   };
   ```
2. S'assurer que le back a `CORS_ORIGIN` qui inclut le domaine du front
3. Verifier HTTPS partout (Leaflet et fetch refusent le mixed content)

### Checklist avant mise en prod

- [ ] `config.local.js` genere avec l'URL de l'API prod
- [ ] Headers `Content-Security-Policy` configures sur le serveur HTTP (recommande : `script-src 'self' unpkg.com fonts.googleapis.com; style-src 'self' fonts.googleapis.com; font-src fonts.gstatic.com; img-src 'self' *.tile.openstreetmap.org data:; connect-src 'self' <API_URL>`)
- [ ] `Strict-Transport-Security` (HSTS) par le serveur HTTP
- [ ] Compression (gzip / brotli) sur `*.js`, `*.css`, `*.svg`, `*.html`
- [ ] Cache long sur `assets/` (immutables) ; court sur `*.html` et `src/*.js` (pour deploiements rapides)

---

## 20) Troubleshooting

### Ecran vide (`<main>` reste vide)

- Console : `Template "screen-..." introuvable.` -> l'id `template` du module d'ecran ne correspond a aucun
  `<template>` de `index.html`.
- Le module d'ecran n'est pas dans la liste passee a `boot()` par `src/pages/app.js` : l'adresse affiche la 404.

### Erreur CORS

**Symptome** : `Access to fetch at 'http://localhost:8000/events' from origin 'http://localhost:5500' has been blocked by CORS policy`

**Solution** : ajouter l'origine du front a `CORS_ORIGIN` dans `back/.env` :

```env
CORS_ORIGIN=http://localhost:5500,http://127.0.0.1:5500
```

Puis redemarrer `php -S` cote back. Localhost et 127.0.0.1 **sont des origines differentes**.

### `window.L is undefined` / "Carte indisponible"

Leaflet n'a pas pu etre charge depuis unpkg. Causes :

1. Pas de connexion internet / CDN bloque
2. SRI casse (version changee sans recalcul du hash) : recalculer avec
   ```bash
   curl -sL https://unpkg.com/leaflet@1.9.4/dist/leaflet.js | openssl dgst -sha384 -binary | openssl base64 -A
   ```
3. CSP du serveur trop stricte : autoriser `unpkg.com`

La liste des evenements reste fonctionnelle meme sans la carte.

### Les icones Lucide ne s'affichent pas

Les icones sont des SVG inline (pas de CDN). Verifier :

1. `icon(name)` est appele avec un nom valide (`"map-pin"`, `"calendar"`...)
2. Le CSS `.icon { width: var(--icon-size); }` est charge

### Le test de contrat echoue

**Symptome** : `contract.test.js` indique des champs manquants

**Cause** : la spec OpenAPI du back a ete modifiee mais pas le typedef front, ou l'inverse.

**Solution** :

1. Verifier que le back tourne (`curl http://localhost:8000/openapi.json`)
2. Comparer les champs de `openapi.json` avec `src/model/dto/eventDto.js`
3. Mettre a jour le typedef, `toEvent`, `toEventDto` en consequence
4. Relancer `node --test tests/contract.test.js`

### Les filtres ne correspondent pas aux resultats attendus

Les filtres de la carte sont **cote client** (`model/domain/eventFilters.js`). Verifier :

1. `isUpcoming(event)` : les events termines sont **exclus** (`end_at < now`, ou `start_at < now` a defaut)
2. "Ce week-end" : samedi 0 h -> **lundi 0 h** (fuseau local du navigateur)
3. Rayon : distance haversine, 1-20 km seulement
4. Recherche : titre, lieu, ville ; sans accents, insensible a la casse

### Diagnostic general

1. **Onglet reseau** (DevTools) : la requete `/events` renvoie bien `200 { data: { items: [...] } }` ?
2. **Console** : erreurs JS (SyntaxError sur un module non charge, ImportError...) ?
3. **Logs back** : `tail -f back/storage/logs/error-$(date +%Y-%m-%d).log`
4. **`X-Request-Id`** : citer cet id dans un rapport de bug permet de retrouver le contexte serveur

---

## 21) Charte graphique

Tokens dans [`assets/css/tokens.css`](assets/css/tokens.css) ; repartition d'un ecran : 70 % neutres / 20 % violet nuit / 10 % orange + categories.

### Couleurs

| Role                                              | Token                                           | Valeur                                                           |
| ------------------------------------------------- | ----------------------------------------------- | ---------------------------------------------------------------- |
| Texte, titres, header/footer                      | `--color-text`, `--color-brand`                 | `#241238` (violet nuit)                                          |
| Fond d'action "sortir" (bouton primaire, curseur) | `--color-action`                                | `#FF771C` : texte dessus **toujours** violet nuit (6,5:1), jamais blanc (2,7:1) |
| Liens, prix, mots orange                          | `--color-link`                                  | `#AE4705`                                                        |
| Fond / cartes / chips                             | `--color-bg`, `--color-surface`, `--color-surface-2` | `#F7F5FA`, `#FFFFFF`, `#EFEBF5`                             |
| Texte secondaire / bordure de champ               | `--color-muted`, `--color-border`               | `#6B6280`, `#8C82A0`                                             |
| Bordure de carte (hors charte)                    | `--color-border-light`                          | `#E4DFEC`, teinte claire de `#8C82A0`, decorative                |
| Etats (toujours avec un mot)                      | `--color-success/-warning/-error/-info`         | `#157A50`, `#946000`, `#BF2B3B`, `#2F62D0`                       |
| Categories (pastilles, carte)                     | `--color-cat-*`                                 | Soiree `#8B3FD9`, Sport `#158F55`, Conference `#2F62D0`, Hackathon `#0E8597`, Culture `#D63A6E`, Forum `#A0700A` |

### Typo (Google Fonts, `<link>` + preconnect dans `index.html`)

- **Fredoka** 600/700 pour les titres (H1 36/42, H2 28/34, H3 22/28)
- **Figtree** 400-700 pour le texte (16/24, detail 14/20, pastille 12 majuscules)

### Formes

- Boutons et pastilles en **pilule**
- Champs **8 px**, cartes **14 px**, bandeaux **24 px**
- Icones **Lucide** 20-24 px, `aria-hidden="true"`

### Focus

- Anneau `--focus-color` + halo `--focus-halo`
- Les **zones violettes** (header, footer, skip-link) inversent les deux pour contraste suffisant

### Ton

- **Tutoiement**, verbes d'action sur les boutons
- Dates francaises ("Sam. 15 nov. · 9 h")
- **Pas d'emoji**

### Logo

Fichier unique [`assets/img/dayout-logo-light.svg`](assets/img/dayout-logo-light.svg) (blanc + orange, pour fond violet nuit), reference **seulement**
dans `view/components/logo/logo.js` (`renderLogo()`, alt `"DayOut"`, 96 px de large minimum). Pour changer de
fichier ou de variante, modifier `LOGO_LIGHT` (et `LOGO_RATIO` si le viewBox change). **Jamais** de logo
tiers dans le header.

### Header

`view/layout/header/header.js` remplit `<header class="site-header" data-layout>` **une fois** : logo, recherche,
menu (`NAV_ITEMS` : Carte, Evenements, Mon planning) et bouton Connexion. Au-dessus de 900 px, le menu est a droite
(lien actif souligne en orange) ; en dessous, ligne 1 = logo + burger, ligne 2 = recherche, et le menu passe dans
un panneau sous le header (`--z-menu`). `--header-min-height` (tokens.css, recalcule dans le `@media` de header.css)
suit la hauteur reelle du header : la carte s'en sert pour remplir l'ecran.

### Footer

`view/layout/footer/footer.js` remplit `<footer class="site-footer" data-layout>` **une fois**, sur tous les ecrans
(carte comprise, sous la carte) : logo (96 px), accroche, `<nav aria-label="Liens utiles">` (`FOOTER_LINKS` : FAQ, CGU,
Mentions legales, Contact, vers les ecrans "Bientot disponible") et la ligne
"Un projet G4B Digital · ISEP · APP Informatique 2026/2027". Pas de `<noscript>` : sans JS, `<main>` affiche un message.

---

## Ajouter un ecran (checklist complete)

### 1. Route

Ajouter le chemin dans `PATHS` (`src/core/routes.js`) ; tout lien vers l'ecran s'ecrit `routeHref(PATHS.monEcran)`.

### 2. Template (dans `index.html`)

```html
<template id="screen-mon-ecran">
  <h1>Titre unique de l'ecran</h1>
  <p id="mon-ecran-error" class="alert alert--error" role="alert"></p>
  <p id="mon-ecran-status" class="status-message" role="status"></p>
</template>
```

Pas de `<head>`, de header ni de footer a recopier : la coquille les fournit.

### 3. Service (si besoin)

```js
// src/model/services/eventService.js
export async function fetchEvent(id, { signal } = {}) {
    return toEvent(await api.get(`/events/${encodeURIComponent(id)}`, { signal }));
}
```

### 4. View

```js
// src/view/MonEcranView.js : seul fichier qui connait les ids du template
import { h } from "../shared/dom.js";

export class MonEcranView {
    constructor(root = document) {
        this.errorEl  = root.querySelector("#mon-ecran-error");
        this.statusEl = root.querySelector("#mon-ecran-status");
    }

    get isReady() {
        return Boolean(this.errorEl && this.statusEl);
    }

    showEvent(event) { /* h(...) + textContent, jamais innerHTML */ }

    showMissing() {
        this.showError("Aucun evenement selectionne.");
    }

    showError(message) {
        this.statusEl.textContent = "";
        this.errorEl.textContent = message;
    }
}
```

### 5. Controleur (avec `stop()`)

```js
// src/controller/MonEcranController.js
import { isApiError, messageFor } from "../core/errors.js";
import { fetchEvent } from "../model/services/eventService.js";
import { MonEcranView } from "../view/MonEcranView.js";

export class MonEcranController {
    constructor({ view = new MonEcranView(), loadEvent = fetchEvent } = {}) {
        this.view = view;
        this.loadEvent = loadEvent;
        this.abort = new AbortController();
        this.stopped = false;
    }

    async start(id) {
        if (!this.view.isReady) return;
        if (!id) {
            this.view.showMissing();
            return;
        }
        try {
            const event = await this.loadEvent(id, { signal: this.abort.signal });
            if (!this.stopped) this.view.showEvent(event);
        } catch (err) {
            if (this.stopped || (isApiError(err) && err.type === "abort")) return;
            if (!isApiError(err)) console.error(err);
            this.view.showError(messageFor(err));
        }
    }

    stop() {
        this.stopped = true;
        this.abort.abort();
    }
}
```

### 6. Module d'ecran et route

```js
// src/pages/monEcran.js
import { PATHS } from "../core/routes.js";
import { MonEcranController } from "../controller/MonEcranController.js";

let controller = null;

export const monEcran = {
    id: "mon-ecran",                 // = id de NAV_ITEMS (header) si l'ecran est dans le menu
    path: PATHS.monEcran,
    title: "Mon ecran",              // onglet ("DayOut — Mon ecran") et annonce du changement d'ecran
    template: "screen-mon-ecran",
    mainClass: "container",
    mount({ params }) {
        controller = new MonEcranController();
        controller.start(params.get("id"));   // #/mon-ecran?id=42
    },
    unmount() {
        controller?.stop();
        controller = null;
    },
};
```

Puis l'ajouter a la liste de `src/pages/app.js` : `boot({ routes: [home, events, monEcran, ...placeholders], notFound })`.
Si l'ecran doit apparaitre dans le menu, ajouter une entree a `NAV_ITEMS` (`view/layout/header/header.js`).

### 7. Tests

Ajouter au moins un test pour :

- Le **mapper** si l'ecran introduit une nouvelle ressource
- Les **regles metier** si l'ecran introduit du calcul client
- Le **contrat** se met a jour tout seul (il lit la spec du back)

---

## Ressources complementaires

- **Back** : [`../back/README.md`](../back/README.md)
- **Infra** (BDD) : [`../infra/README.md`](../infra/README.md)
- **OpenAPI brute** : `http://localhost:8000/openapi.json`
- **Swagger UI** : `http://localhost:8000/docs`

---

## Licence

Projet academique ISEP A1 — equipe listee dans le [README racine](../README.md).
