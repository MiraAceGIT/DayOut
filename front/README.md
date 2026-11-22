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

**V1 publique en lecture seule** — Trois pages implementees :

- `index.html` : carte interactive "Que faire autour de toi ?" (plein ecran)
- `/events` (`pages/event/events.html`) : liste des evenements a venir (cartes + footer)
- `/event?id=1` (`pages/event/event.html`) : detail dynamique et ecran 404 pour un evenement inaccessible
- `/register` (`pages/user/register.html`) : creation de compte participant ou createur (SCRUM-41)
- `/confirm-email?token=...` (`pages/user/confirm-email.html`) : confirmation de l'adresse par le lien recu

La connexion (login, pages protegees), les inscriptions aux evenements et l'admin sont en cours
(infrastructure deja en place : `app/boot.js` installe un handler `401`, `loginUrl()` genere l'URL de connexion).

### Perimetre exact

**Inclus** :

- Carte Leaflet (OpenStreetMap) avec marqueurs, bulle, legende
- Liste filtree cote client : rayon (1-20 km), week-end, gratuit, places disponibles, categories, recherche plein texte
- Geolocalisation navigateur (sur clic uniquement, respecte `prefers-reduced-motion`)
- Zone de couverture Ile-de-France (contour geographique exact)
- Client API avec enveloppe, timeout, gestion CSRF, redirection 401
- Composants reutilisables : header, footer, cartes, pastilles, chips, onglets ARIA
- Tests unitaires (`node:test`) + test de contrat contre la spec OpenAPI du back
- Verification des types JSDoc via `tsc` (`model/`, `shared/`, `core/`, `controller/`, tests)
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

### Pages disponibles

- **`/`** (`index.html`) : carte "Que faire autour de toi ?" (accueil plein ecran)
- **`/events`** (`pages/event/events.html`) : liste des evenements a venir (cartes + footer)
- **`/event?id=1`** (`pages/event/event.html`) : detail dynamique via `GET /events/1`, d'apres l'ecran 02 des maquettes. Le header et le footer utilisent le gabarit partage ; les blocs meteo/transports/mini-carte/discussion restent reserves et les actions desactivees.
- **`/register`** (`pages/user/register.html`) : inscription (voir *Inscription* en section 7)
- **`/confirm-email?token=...`** (`pages/user/confirm-email.html`) : page ouverte par le lien du mail de confirmation

---

## 6) Architecture du code (MVC + transverses)

### Structure

```
front/
├── index.html                    ← carte "Que faire autour de toi ?" (plein ecran, sans footer)
├── pages/                        ← HTML des pages (seul le <main> est affiche, par le routeur)
│   ├── home.html, faq.html, not-found.html
│   ├── event/                    ← events.html (liste), event.html (detail)
│   └── user/                     ← register.html (inscription), confirm-email.html (lien du mail)
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
│   ├── core/                     ← infrastructure : config, api, apiLimits (plafonds de l'API), errors, geolocation, routes
│   ├── app/                      ← boot.js (header + footer + handler 401), router.js, navigation.js (decisions du routeur, pures), appRoutes.js (liste des pages)
│   ├── shared/                   ← utilitaires purs sans dependance : dom (h), format, geo, highlight, text (normalize), LatestTask (derniere tache),
│   │                               forms (erreurs sous les champs d'un formulaire)
│   ├── model/                    ← M : sans DOM
│   │   ├── dto/                  ← typedefs JSDoc du JSON API (snake_case), un fichier par ressource
│   │   ├── mappers/              ← DTO <-> modele (camelCase), seuls a lire les noms JSON ;
│   │   │   ├── event/            ← eventMapper, eventRequestMapper (requetes GET /events)
│   │   │   ├── faq/              ← faqMapper, faqRequestMapper (requetes GET /faq)
│   │   │   ├── user/             ← userMapper (compte cree, confirmation), userRequestMapper (corps de POST /users)
│   │   │   └── categoryMapper, creatorMapper, schoolMapper, pageQueryMapper (limit/offset)
│   │   ├── services/             ← appels API REST via core/api.js, retournent des modeles ; ne construisent aucune URL
│   │   ├── domain/               ← regles metier pures (ni reseau, ni DOM, ni etat)
│   │   │   ├── event/            ← event (type Event, places), eventFilters, eventSearch, eventSuggestions
│   │   │   ├── coverageArea/     ← coverageArea, ileDeFranceOutline (contour)
│   │   │   └── category, faqSearch, keyword, registration (regles du formulaire d'inscription)
│   │   └── state/                ← etat d'une page (ex. MapSearch : criteres, chargement, selection)
│   ├── view/                     ← V : construisent et mettent a jour le DOM
│   │   ├── layout/header/        ← renderHeader()
│   │   ├── layout/footer/        ← renderFooter()
│   │   ├── components/           ← category, cover, logo, icon, pill, tabs, liveStatus, searchBox ;
│   │   │   └── event/            ← eventCard, eventMeta, eventRow, eventPopup
│   │   ├── home/                 ← HomeView, homeMessages, PointCard, FilterChips, ResultsList, MapView (LeafletMap + MapLegend + MapStatus)
│   │   ├── event/                ← liste (EventsView, eventsMessages) et detail (EventDetailView, eventDetailPresenter)
│   │   ├── faq/                  ← FaqView, faqMessages
│   │   └── user/                 ← RegisterView, registerMessages, ConfirmEmailView, confirmEmailMessages, user.css
│   ├── controller/               ← C : mediateurs minces (Home, Faq ; event/ : Events, EventDetail ; user/ : Register, ConfirmEmail) ;
│   │                               PageLoader (cycle de chargement), OriginPicker (point de depart)
│   ├── pages/                    ← points d'entree minces (un par page HTML), appeles par le routeur ; event/ : event, events ;
│   │                               user/ : register, confirmEmail
│   └── types/globals.d.ts        ← declarations globales (DAYOUT_CONFIG, Leaflet)
├── tests/                        ← node:test, aucune dependance npm ; event/, user/ : tests de ces ressources
├── tools/typecheck.sh            ← installe TypeScript dans .cache/ et verifie les JSDoc
├── jsconfig.json                 ← verification JSDoc de src/ (VS Code + tsc en CI) ; tests/jsconfig.json pour les tests
└── README.md
```

**Un dossier par ressource** : dans un dossier, des qu'une ressource a plus d'un fichier, ils vont dans un
sous-dossier a son nom (`model/mappers/event/`, `controller/event/`, `view/components/event/`, `tests/event/`...).
Une ressource a un seul fichier reste a plat (`model/services/eventService.js`, `model/domain/keyword.js`).

### Architecture MVC

Un dossier par couche : `model/`, `view/`, `controller/`. `pages/`, `app/`, `core/` et `shared/` sont
transverses (points d'entree, demarrage, infrastructure, utilitaires).

- **model/** : tout ce qui est sans DOM.
  - **dto/** : forme exacte du JSON echange avec l'API, convention du back (snake_case), miroir de `back/src/Model/Dto`. Typedefs JSDoc uniquement, aucun code.
  - **mappers/** : seuls a connaitre les noms du JSON. `toEvent(dto)` construit l'objet du front (type `Event` de `model/domain/event/event.js` : camelCase, `position: {lat, lng}`, places restantes calculees). `toEventDto(event)` fait l'inverse : il sert au test de contrat (le front connait tous les champs) et servira aux formulaires.
  - **services/** : acces a l'API REST (via `core/api.js`). Equivalent client de `back/src/Model/Dao`. Un service par **ressource appelee** (`/events` -> `eventService.js`), pas par modele.
  - **domain/** : regles metier pures (a venir, places restantes, filtres, zone couverte). Aucun DOM, aucun texte affiche, aucune couleur, aucun nom du JSON, aucun appel reseau, aucun etat.
  - **state/** : etat d'une page (`MapSearch` : evenements charges, criteres, selection), s'appuie sur `domain/`.
- **view/** : construisent et mettent a jour le DOM (`h()`, `textContent`) et portent **tous** les textes et choix d'affichage. Aucun appel API, aucune regle metier. Une view de page est seule a connaitre ses ids et les passe a des sous-vues sans id, reutilisables. Les textes calcules (statuts, page detail) sont des **fonctions pures a cote de la view** (`xxxMessages.js`, `eventDetailPresenter.js`), testees sans DOM.
- **controller/** : mediateurs minces (relaient les evenements des views vers le model, redessinent). Ne touchent **jamais** le DOM ni l'adresse, ne contiennent ni texte affiche ni regle metier. Dependances injectables par le constructeur (tests). Tous demarrent **une seule fois** par `start({ signal, ...params })` (garde `started`) ; un chargement passe par `PageLoader` (un a la fois, message d'erreur, rien apres le depart de la page). Les views recoivent ce `signal` dans `bind(handlers, signal)` : leurs ecouteurs et annonces differees s'arretent au depart de la page.
- **pages/** : points d'entree minces, un par page HTML. Le routeur appelle `mount({ root, signal, params, setParam })` (typedef `PageContext` de `app/router.js`) :
  - `root` : le `<main id="app-root">` ou la page est posee ; la page construit ses views avec (`new EventsView(root)`), elles n'y cherchent que leurs elements ;
  - `signal` : annule quand on quitte la page ;
  - `params` : la query string ;
  - `setParam(name, value)` : remplace un parametre de l'adresse sans recharger (ex. `?q=` de la carte). **Seul le routeur modifie l'adresse** : sinon il ne saurait plus quelle page est affichee (un lien vers l'adresse d'avant ne ferait rien).

  Une erreur de `mount` est journalisee dans la console. Le header, le footer et le handler 401 sont poses une fois par `app/boot.js`.
- **Fonctions** pour ce qui est sans etat (models, composants) ; **petite classe** pour ce qui garde des references (view et ses elements, etat de page, controleur).

### Regle de dependances

```
app/             -> pages/ (appRoutes.js), core/, shared/dom.js, view/layout/
pages/           -> controller/, view/ (views de la page, construites avec `root`)
controller/      -> model/services/, model/state/, model/domain/, model/mappers/pageQueryMapper.js (normalizePageLimit),
                    view/, core/ (sauf api.js), shared/LatestTask.js
model/services/  -> core/api.js, model/mappers/, model/dto/ (types)
model/mappers/   -> model/domain/ (regles de calcul), model/dto/ (types), core/apiLimits.js
model/state/     -> model/domain/
model/domain/    -> model/domain/, shared/geo.js, shared/text.js (calcul pur)
view/            -> view/, shared/, core/config.js (frontUrl), core/routes.js (loginUrl, routeUrl), core/apiLimits.js
core/            -> core/ uniquement
shared/          -> shared/ uniquement (utilitaires purs)
```

- Seul `app/appRoutes.js` importe `pages/` ; pas de dependance circulaire
- Une view n'importe **ni** `model/` **ni** `core/api.js` (un `import()` de type JSDoc est permis) ; un model n'importe **ni** `view/` **ni** `shared/dom.js`
- Les plafonds de l'API (`limit`, `offset`, longueur de `q`) ne sont ecrits que dans `core/apiLimits.js`
- Ce dont une view a besoin cote metier est un **champ de l'objet normalise** (`event.remainingPlaces`, `event.availability`) ou lui est **passe par le controleur**
- Leaflet n'est lu que dans `view/home/map/LeafletMap.js`

---

## 7) Pages & composants

### Page carte (`index.html`)

- Les evenements sont charges **une fois** (`limit=100`, plafond du back) puis filtres **cote client** (`model/domain/event/eventSearch.js`) :
  - distance haversine au point choisi
  - rayon 1-20 km
  - criteres de `model/domain/event/eventFilters.js` : "Ce week-end" (samedi 0 h -> lundi 0 h), "Gratuit", "Places disponibles", categories (OU), recherche du header (titre, lieu, ville ; sans accents ni casse)
- Un evenement **sans coordonnees** reste dans la liste ("position inconnue"), n'est pas dessine et ne compte pas dans "N evenements dans ce cercle" (le statut ajoute "· N sans position sur la carte")
- La **liste** est le contenu accessible principal ; la carte est un complement : marqueurs **hors tabulation**, carte focusable (fleches, +/−, **Entree** = placer le point au centre). Le statut `role="status"` annonce le compte.
- Evenements termines exclus cote client (`isUpcoming` : fin ou debut passe). Si la reponse atteint le plafond de 100, le statut precise "au moins 100 evenements charges, liste peut-etre incomplete".
- Les controles memorisent les criteres **pendant** le chargement mais la liste n'est dessinee qu'une fois les donnees chargees (jamais "Aucun evenement" pendant le chargement ou apres une erreur).
- Statut `role="status"` mis a jour 400 ms apres la derniere frappe / le dernier cran du curseur (la liste, elle, est immediate) ; la recherche est recopiee dans `?q=` (`history.replaceState`).
- **Geolocalisation** demandee **uniquement** au clic sur "Me localiser" ; refus ou erreur -> message dans `#home-map-status`.
- Recherche du header : formulaire GET vers `index.html?q=` ; sur la carte, elle filtre en direct sans rechargement.
- Onglet "Pour toi" : trois suggestions publiques d'evenements a venir avec des places, triees par date parmi les donnees deja chargees. Le texte distingue cette selection des futures recommandations personnalisees. Le chargement et les erreurs sont affiches dans les deux onglets, sans requete API supplementaire pour les suggestions.
- **Leaflet 1.9.4** en `<script>` classique UMD + `<link>` CSS depuis unpkg, versions epinglees avec `integrity` (SRI). `LeafletMap` lit `window.L` ; s'il manque (hors ligne, bloque), un message s'affiche et la liste reste utilisable.
- **Categories** : `view/components/category/category.js` rattache les slugs charte (`soiree`, `sport`, `conference`, `culture`, `tech`, `forum-entreprises`) a leur couleur ; les autres slugs (`concert`, `gaming`, `bde`, `exterieur`, `gastronomie`) sont gris et regroupes sous "Autres" dans la legende. Couleur d'un evenement = sa premiere categorie de la charte, sinon sa premiere categorie.

### Page liste (`pages/event/events.html`)

- Cartes complete par evenement (image, titre, date formatee, lieu, prix, places, categories)
- Chargement dynamique par pages de 20 via `GET /events?limit=20&offset=...&from=...` ; bouton "Voir plus" masque quand `has_more` vaut `false`.
- L'instant `from` reste fixe pendant la consultation ; une erreur conserve les cartes deja affichees et propose "Reessayer" au meme offset. Les requetes simultanees sont bloquees ; le plafond d'offset de l'API est respecte.
- Pas de filtres ni de carte
- Footer visible

### Inscription (`pages/user/register.html`)

- Profil **participant** ou **createur** (cartes radio) ; createur : message « compte soumis a validation », nom de
  l'association / du club / du BDE ou case « a titre individuel », ecole de rattachement = l'ecole choisie.
- Champs : nom, prenom, e-mail de l'ecole, ecole (liste de `GET /schools`), mot de passe + confirmation (jamais envoyee),
  centres d'interet (cases de `GET /categories`, au moins une), rayon de recherche (1-20 km, 3 par defaut), CGU.
- **Validation en direct** (`model/domain/registration.js`, fonctions pures) : un champ est verifie des qu'on l'a quitte,
  puis a chaque frappe ; tous apres un premier envoi. Format de l'e-mail, domaine d'une ecole partenaire, coherence
  domaine <-> ecole choisie, mot de passe (8 caracteres, une majuscule, un chiffre), confirmation identique, CGU, au
  moins un interet. Jauge de force du mot de passe (texte + barre, jamais la couleur seule).
- Les regles renvoient des **codes** (`domain_not_allowed`, `too_short`...) ; les textes sont dans `view/user/registerMessages.js`.
- Erreurs sous chaque champ (`aria-invalid` + `aria-describedby`, `shared/forms.js`), focus sur la premiere a l'envoi.
  Le formulaire a `novalidate` : ce sont ces messages, pas ceux du navigateur, qui s'affichent.
- Le serveur reverifie tout : une `422` met les champs refuses en erreur, `409 EMAIL_TAKEN` s'affiche sous l'e-mail,
  une panne sous le bouton ; la saisie reste en place.
- Succes : « Verifie ta boite mail » (lien valable 24 h ; demande createur examinee par un admin). Avec `MAIL_DEMO=true`
  cote back, le lien de confirmation s'affiche aussi (demo sans serveur de mail).
- **Confirmation** (`/confirm-email?token=`) : la page envoie le jeton en `POST /email-verifications` (jamais un `GET`
  qui modifie) et affiche compte active, demande createur en attente, lien expire, deja utilise ou invalide ; une panne
  propose « Reessayer » (le lien n'a pas ete consomme). Une fois l'issue connue, le jeton est retire de l'adresse
  (`setParam`). Lien expire : pas de bouton « Creer un compte », l'adresse appartient deja au compte non confirme
  (le renvoi d'un nouveau lien n'est pas dans ce ticket).
- Refus rares du serveur aussi affiches sous l'e-mail : `409 CONFLICT` (deux inscriptions simultanees) et
  `422 EMAIL_DOMAIN_NOT_ALLOWED` (ecole desactivee pendant la saisie, refusee par le trigger).
- Page sans lien dans le header pour l'instant : on y arrive par `/register` (lien a ajouter avec la connexion, SCRUM-42).

### Composants reutilisables (`src/view/components/`)

| Composant     | Role                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `category`    | slug -> classe CSS, couleur, pastille ; seul lien categorie -> couleur |
| `logo`        | `renderLogo()`, seul endroit qui connait le fichier du logo         |
| `icon`        | `icon(name)`, `pinIcon()` : SVG Lucide via `createElementNS`         |
| `pill`        | `renderCategoryPill`, `renderPricePill`, `renderPlacesPill` (+ `.pill`) |
| `eventMeta`   | `joinMeta`, `distanceText` (lignes de details)                       |
| `cover`       | `setCover(img, url)` : couverture de l'API si son URL est sure, sinon illustration par defaut (aussi si l'image ne charge pas) |
| `searchBox`   | `renderSearchBox()` (header, accueil) et `SearchBox` (champs synchronises, relus a chaque appel) |
| `eventCard`   | rendu commun `.event-card` : variantes `grid`, `compact`, `popup` ; image avec remplacement, titre, categories, date, lieu, prix, places et lien detail |
| `eventRow`    | adaptateur `<li>` vers `eventCard` compact (selection synchronisee)   |
| `eventPopup`  | adaptateur vers `eventCard` popup avec action d'inscription           |
| `tabs`        | `bindTabs(tabs)` : onglets ARIA sur markup existant (+ `.tabs`)      |
| `liveStatus`  | `LiveStatus` : region `role=status`, texte immediat ou differe       |

Pour afficher des cartes sur une autre page (recherche ou suggestions), reutiliser
`renderEventCard(event)` avec un evenement normalise par le mapper. Ne pas recopier
le HTML ou creer une autre classe de carte. Les trois variantes conservent `.event-card` :

Le partial `carte_evenement.php` mentionne dans le ticket correspond ici a
`src/view/components/event/eventCard/eventCard.js` : le front HTML/JS consomme l'API PHP
et partage `renderEventCard()` pour tous les contextes.

| Contexte | Chemin de rendu vers le composant commun |
| --- | --- |
| Accueil | `HomeView` -> `ResultsList` -> `renderEventRow` -> `renderEventCard` compact |
| Liste | `EventsView` -> `renderEventCard` grille |
| Recherche | `HomeController.search()` -> memes resultats de l'accueil, meme carte compacte |
| Suggestions | `HomeView` -> `SuggestionsList` -> `renderEventCard` grille |

La selection des suggestions est une fonction pure `suggestEvents()` dans le modele.
`SuggestionsList` ne gere que le rendu, avec un etat vide et une erreur distincts.

```js
import { renderEventCard } from "../components/event/eventCard/eventCard.js"; // depuis src/view/<page>/

// Vue de page : la grille utilise le rendu complet par defaut.
listEl.replaceChildren(...events.map((event) => renderEventCard(event)));
```

L'image absente, invalide ou inaccessible est remplacee par un visuel local neutre.
Les titres pointent vers `/event?id=...`, qui charge l'evenement correspondant depuis l'API.

Le service `fetchEvent(id)` et le mapper `toEventDetail()` assurent ce branchement :
ils lisent `GET /events/{id}` et ajoutent le profil public `creator` normalise
(`id`, `displayName`, `description`, `avatarUrl`, `schoolName`). Le contrat du feed
reste distinct et conserve uniquement les champs d'`EventDto`.

### Detail et etats d'erreur

Un identifiant absent, vide ou duplique (`?id=1&id=2`) affiche l'ecran 404 sans requete.
Sinon, l'identifiant de l'URL part tel quel a l'API (encode dans le chemin, garde en chaine pour
eviter la perte de precision JavaScript) : c'est elle qui le valide. Sa reponse 404 affiche le meme
ecran pour un identifiant invalide et pour un evenement inexistant, brouillon, annule ou archive.

`EventDetailView` cache les informations pendant le chargement et lors d'une erreur.
L'ecran 404 comporte un retour vers la liste, un titre de document explicite et un
focus sur son titre. Une panne reseau ou serveur affiche une erreur distincte avec
"Reessayer". Les donnees sont inserees avec `textContent` et les utilitaires DOM.

Exemples : `/event?id=abc` et `/event?id=9999`. Le second cas
necessite une API joignable pour confirmer que l'evenement n'existe pas.
Le front est servi comme fichiers statiques : le document HTML conserve le statut
HTTP 200 et affiche l'ecran 404 en JavaScript ; l'API renvoie bien le statut HTTP 404.

---

## 8) Modeles & regles metier (`src/model/domain/`)

### Fichiers

- **`event.js`** : typedef `Event` (l'evenement du front), `remainingPlaces`, `availabilityOf`, `LAST_PLACES_THRESHOLD`, `isUpcoming`
- **`keyword.js`** : `cleanKeyword` — mot-cle `q` envoye a l'API (sans espaces autour, coupe a 100 caracteres), seul endroit de cette regle
- **`category.js`** : `categoriesIn` (aucune notion de couleur, que des slugs)
- **`coverageArea.js`** : `COVERAGE_AREA` (Ile-de-France), `isInCoverageArea` (contour exact)
- **`ileDeFranceOutline.js`** : contour de la region (genere, resolution ~300 m)
- **`eventFilters.js`** : un predicat par critere (gratuit, places, categories, week-end, texte), `USER_FILTERS`, `matchesCriteria`, `weekendRange`
- **`eventSearch.js`** : `searchEvents` — a venir + criteres + distance/rayon + tri + comptes ; typedef `SearchItem` (`{ event, distanceKm }`)
- **`eventSuggestions.js`** : `suggestEvents` — selection publique de trois evenements a venir non complets, issue du meme chargement que l'accueil
- **`registration.js`** : regles du formulaire d'inscription, miroir de `UserCreateDto` (back) : `emailDomain`, `schoolForEmail`,
  `passwordIssue`, `passwordStrength` (0-4, pour la jauge), `validateRegistration(values, schools)` -> `{ champ: code }`

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
// model/domain/event/event.js
export const LAST_PLACES_THRESHOLD = 5;

export function remainingPlaces(capacity, registeredCount) {
    if (capacity === null || capacity === undefined) return null;
    return Math.max(0, Number(capacity) - Number(registeredCount || 0));
}

export function availabilityOf(left) {
    if (left === null) return null;
    if (left === 0) return "full";
    if (left <= LAST_PLACES_THRESHOLD) return "last";
    return "open";
}
```

Les views lisent `event.availability` pour choisir leur rendu (`"Complet"`, `"Dernieres places"`...).

Le mapper calcule les places a partir de `capacity` et du `registered_count` fourni
par l'API (recalcule depuis les inscriptions actives pour le detail). Un depassement
donne zero place restante ; une capacite `null` donne des places illimitees.
`formatRemainingPlaces()` partage les libelles entre cartes et detail : "Complet",
"1 place restante", "3 places restantes", "Places illimitees". La jauge est masquee
en capacite illimitee et le bouton affiche "Complet" lorsqu'il ne reste aucune place.

### Dates affichees en francais

Les dates restent en ISO 8601 UTC dans les donnees. `shared/format.js` utilise
`fr-FR` et le fuseau `Europe/Paris`, quel que soit le fuseau du navigateur, avec les
changements d'heure d'ete/hiver geres par `Intl.DateTimeFormat`.
`formatEventWhen()` affiche la fin quand `withEnd` vaut `true`, avec les deux dates
si l'evenement traverse minuit a Paris. Le detail affiche aussi l'annee et "Heure de Paris" ;
les cartes ajoutent automatiquement les annees si la plage change d'annee.

Exemples : "Dim. 15 nov. 2026 · 9 h – 21 h" sur le detail,
"Sam. 7 nov. · 21 h – Dim. 8 nov. · 3 h" pour une soiree sur deux jours.

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
- Perimetre : tout `src/` (routeur, pages, views comprises) avec `strictNullChecks` : un element du DOM ou un champ qui peut manquer doit etre teste ou explicitement type (les views typent leurs elements non nuls, garantis par `isReady`)
- Les tests ont leur config (`tests/jsconfig.json`, sans `strictNullChecks` : leurs assertions garantissent deja les valeurs)
- `tools/typecheck.sh` installe TypeScript (version epinglee) dans `tools/.cache/` (ignore par git)

---

## 10) Mappers

### Localisation

- [`src/model/mappers/event/eventMapper.js`](src/model/mappers/event/eventMapper.js) : `toEvent(dto)`, `toEvents(dtos)`, `toEventDto(event)`
- [`src/model/mappers/categoryMapper.js`](src/model/mappers/categoryMapper.js) : `toCategory(dto)`, `toCategoryDto(category)`
- [`src/model/mappers/pageQueryMapper.js`](src/model/mappers/pageQueryMapper.js) : `toPageQuery({ limit, offset })` (pagination commune a toutes les listes, bornes de `core/apiLimits.js`)
- [`src/model/mappers/event/eventRequestMapper.js`](src/model/mappers/event/eventRequestMapper.js) : `toEventQuery({ limit, offset, from, keyword })` (query string de `GET /events`), `toEventPath(id)` (`/events/{id}`, identifiant encode)
- [`src/model/mappers/faq/faqRequestMapper.js`](src/model/mappers/faq/faqRequestMapper.js) : `toFaqQuery({ limit, offset })` (query string de `GET /faq`)
- [`src/model/mappers/user/userRequestMapper.js`](src/model/mappers/user/userRequestMapper.js) : `toUserCreateDto(values)` (corps de `POST /users`, sans la confirmation du mot de passe), `toEmailVerificationCreateDto(token)`, `toFormFields(details)` (champs d'une 422 -> champs du formulaire)
- [`src/model/mappers/user/userMapper.js`](src/model/mappers/user/userMapper.js) : `toRegisteredUser(dto)`, `toEmailVerification(dto)` ; [`schoolMapper.js`](src/model/mappers/schoolMapper.js) : `toSchool(dto)`

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
  - `remainingPlaces` et `availability` calcules via `domain/event/event.js`
  - Dates `Date` (plutot que strings ISO)
- `toEventDto` **ne renvoie pas** les champs calcules (pas d'aller-retour)
- Dans l'autre sens, les criteres de lecture deviennent des parametres d'API dans les mappers de requete (`eventRequestMapper.js`, `faqRequestMapper.js`) : le service ne construit lui-meme ni les noms `limit`, `offset`, `from`, `q` ni le chemin `/events/{id}`

Le test de contrat `tests/contract.test.js` echoue si un champ du JSON back n'est pas lu par `toEvent` ou pas renvoye par `toEventDto`.

---

## 11) Services (acces API REST)

### Localisation

Fichiers dans [`src/model/services/`](src/model/services/) :

- [`eventService.js`](src/model/services/eventService.js) : `fetchUpcomingEventPage` (une page de `GET /events`), `fetchUpcomingEvents` (ses elements), `fetchEvent(id)` (`GET /events/{id}` ; l'API repond 404, donc une `ApiError` 404, pour un identifiant invalide)
- [`faqService.js`](src/model/services/faqService.js) : `fetchFaq()` (toutes les questions actives, page par page)
- [`userService.js`](src/model/services/userService.js) : `registerUser(values)` (`POST /users`), `confirmEmail(token)` (`POST /email-verifications`), `rejectedRegistrationFields(error)`
- [`schoolService.js`](src/model/services/schoolService.js) : `fetchSchools()` (`GET /schools`) ; [`categoryService.js`](src/model/services/categoryService.js) : `fetchCategories()` (`GET /categories`)
- Plafonds de l'API (`MAX_PAGE_LIMIT`, `MAX_PAGE_OFFSET`, `MAX_KEYWORD_LENGTH`) : [`core/apiLimits.js`](src/core/apiLimits.js)

### Regle

**Un service par ressource appelee** (`/events` -> `eventService.js`), **pas par modele** :
les categories d'un evenement arrivent avec lui ; `categoryService` ne sert qu'a `GET /categories` (inscription).

### Exemple

```js
// src/model/services/eventService.js : le mapper de requete construit la query, le mapper de reponse le modele
import { api } from "../../core/api.js";
import { toEvents } from "../mappers/event/eventMapper.js";
import { toEventQuery } from "../mappers/event/eventRequestMapper.js";

export async function fetchUpcomingEventPage({ limit, offset = 0, from, query: keyword = "", signal } = {}) {
    const page = await api.get(`/events?${toEventQuery({ limit, offset, from, keyword })}`, { signal });
    return { items: toEvents(page.items), limit: page.limit, offset: page.offset, hasMore: page.has_more, total: page.total ?? null };
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
- `setUnauthorizedHandler(fn)` : appele sur tout 401 avant de lever l'erreur. `app/boot.js` l'installe pour rediriger vers `pages/login.html?next=...` (`loginUrl()` dans `core/routes.js`).
- **Timeout par defaut 10 s** (`timeout: 0` pour desactiver), combinable avec un `signal` appelant.

---

## 12) Controleurs (mediateurs minces)

### Localisation

- [`src/controller/HomeController.js`](src/controller/HomeController.js) : page carte ; s'appuie sur [`LatestTask`](src/shared/LatestTask.js) (seule la derniere recherche compte) et [`OriginPicker`](src/controller/OriginPicker.js) (clic sur la carte ou position de l'appareil)
- [`src/controller/event/EventsController.js`](src/controller/event/EventsController.js) : page liste
- [`src/controller/event/EventDetailController.js`](src/controller/event/EventDetailController.js) : page detail
- [`src/controller/FaqController.js`](src/controller/FaqController.js) : FAQ
- [`src/controller/PageLoader.js`](src/controller/PageLoader.js) : cycle d'un chargement (un a la fois, chargement -> succes ou message, rien apres le depart de la page)

### Regles

- **Ne touchent jamais le DOM** (les views s'en chargent)
- **Ne contiennent ni texte affiche ni regle metier** (les views et `domain/` s'en chargent)
- Dependances **injectables par le constructeur** (views, `loadEvents`, `getPosition`...) avec valeurs par defaut
- Pas de conteneur d'injection : le constructeur suffit
- Demarrage uniforme : `start({ signal, ...params })` ; `signal` (depart de la page) est transmis a l'API et aux ecouteurs
- Un chargement passe par `PageLoader.run(task, { onSuccess, onError })` : pas de `try/catch` recopie

### Exemple

```js
// src/controller/event/EventDetailController.js (extrait)
export class EventDetailController {
    constructor({ view = new EventDetailView(), loadEvent = fetchEvent } = {}) {
        this.view = view;
        this.loadEvent = loadEvent;
        this.loader = new PageLoader(view);
    }

    async start({ id = null, signal } = {}) {
        if (!this.view.isReady || this.started || signal?.aborted) return;
        this.started = true;
        this.loader = new PageLoader(this.view, { signal });
        this.id = id || null; // absent : 404 sans requete ; sinon l'API valide
        this.view.bind({ onRetry: () => this.load() }, signal);
        await this.load();
    }

    async load() {
        await this.loader.run((signal) => this.loadEvent(this.id, { signal }), {
            onSuccess: (event) => this.view.showEvent(event),
            // erreur attendue traitee ici ; sinon PageLoader affiche messageFor(err)
            onError: (err) => isApiError(err) && err.status === 404 && (this.view.showNotFound(), true),
        });
    }
}
```

### Point d'entree (page)

```js
// src/pages/event/event.js : appele par le routeur (app/appRoutes.js) une fois pages/event/event.html affiche
import { EventDetailController } from "../../controller/event/EventDetailController.js";
import { EventDetailView } from "../../view/event/EventDetailView.js";

export function mountEvent({ root, signal, params }) {
    const ids = params.getAll("id");
    return new EventDetailController({ view: new EventDetailView(root) })
        .start({ id: ids.length === 1 ? ids[0] : null, signal });
}
```

---

## 13) Gestion des erreurs

### Erreurs d'API — `ApiError`

Levee par `core/api.js` ; les controleurs la rattrapent et affichent via la view.

```js
// Le plus souvent via PageLoader ; a la main :
try {
    const events = await fetchUpcomingEvents({ signal });
} catch (err) {
    if (isApiError(err) && err.status === 404) this.view.showNotFound();   // erreur attendue
    else {
        if (!isApiError(err)) console.error(err);                         // bug
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

Une erreur 422 arrive en `ApiError` avec `isValidation === true` et `details` = champ -> message.
`shared/forms.js` (repris de l'historique git avec le premier formulaire, l'inscription) affiche les erreurs :
`showFieldErrors(form, { champ: message }, { focus })` pose `aria-invalid="true"` et un `<p class="field-error">`
relie par `aria-describedby` sous chaque champ (sous le dernier d'un groupe de cases), et renvoie les erreurs sans
champ correspondant ; `clearFieldErrors(form)` les retire. Les messages du back sont en anglais : la view affiche
ses propres textes, choisis par le controleur avec un code par champ (`registerMessages.js`).

### Redirection 401

`app/boot.js` installe un handler `setUnauthorizedHandler` qui redirige vers
`pages/login.html?next=<URL courante>` sur tout 401. Les appels API echouent **avant** d'atteindre le controleur sur 401 -> l'utilisateur est redirige directement.

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
| GET     | `/events`      | `fetchUpcomingEventPage` (liste), `fetchUpcomingEvents` (carte) |
| GET     | `/events/{id}` | `fetchEvent` (detail)         |
| GET     | `/faq`         | `fetchFaq` (FAQ)              |
| GET     | `/schools`     | `fetchSchools` (inscription)  |
| GET     | `/categories`  | `fetchCategories` (inscription) |
| POST    | `/users`       | `registerUser` (inscription)  |
| POST    | `/email-verifications` | `confirmEmail` (lien du mail) |
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
├── event/
│   ├── eventMapper.test.js          ← aller-retour DTO <-> modele
│   ├── eventSearch.test.js          ← filtres, recherche, distance, tri
│   ├── eventAvailability.test.js    ← places restantes, disponibilite
│   ├── eventSuggestions.test.js     ← suggestions de l'onglet "Pour toi"
│   ├── eventService.test.js         ← requetes GET /events (eventRequestMapper) et service
│   ├── eventDetail.test.js          ← mapper du detail (organisateur)
│   ├── eventDetailController.test.js, eventsPagination.test.js ← controleurs avec doubles de view
│   └── eventNavigation.test.js      ← routeur complet : changement de page, Precedent/Suivant, setParam, erreur de mount
├── user/
│   ├── registration.test.js         ← regles du formulaire, mappers, services (fetch simule)
│   ├── registerController.test.js   ← inscription et confirmation avec doubles de view
│   └── userMessages.test.js         ← textes des erreurs, de la jauge et des issues de confirmation
├── shared.test.js           ← dom (h), format (dates/prix/distance), geo (haversine)
├── keywordSearch.test.js    ← recherche par mot-cle, surlignage
├── pageLoader.test.js       ← cycle de chargement, depart de la page
├── originPicker.test.js     ← LatestTask, choix du point de depart
├── navigation.test.js       ← decisions du routeur (liens, formulaires, parametres d'adresse)
├── viewMessages.test.js     ← textes de statut et presenter de la page detail
├── homeSearchController.test.js, faq.test.js ← controleurs avec doubles de view
├── contract.test.js         ← contrat avec la spec OpenAPI du back
└── fixtures.js              ← eventDto(), event() : donnees communes
```

### Perimetre

- **Mappers** : `toEvent`, `toEventDto`, aller-retour bidirectionnel
- **Domain** : filtres, recherche de la carte, `isUpcoming`, zone couverte, places restantes
- **Shared** : `h()` (XSS, URLs refusees), formats (dates, prix, distance, pluralize), `distanceKm`, `isLatLng`
- **Views** : leurs textes calcules (`*Messages.js`, `eventDetailPresenter.js`) sont testes ; le DOM l'est indirectement
- **Controleurs** : avec des doubles de view ; `PageLoader`, `LatestTask`, `OriginPicker` testes directement
- **Routeur** : ses decisions (`app/navigation.js`) en fonctions pures, et le routeur entier dans un navigateur simule

### Test de contrat (`contract.test.js`)

- La spec OpenAPI du back (assemblee par PHP) est la **reference**
- Echoue si un champ de `Event` / `Category` **manque** au typedef de `src/model/dto/`, **n'est pas lu** par `toEvent` ou **pas renvoye** par `toEventDto`
- Inscription : le corps de `toUserCreateDto` a exactement les champs de `UserCreate` ; `RegisteredUser`, `EmailVerification` et `School` suivent leurs typedefs et sont lus par leurs mappers
- **Ignore sans PHP**, sauf avec `REQUIRE_CONTRACT=1` (force l'echec si PHP manque — utilise en CI)

### Ajouter un test

```js
// tests/event/eventFilters.test.js
import { test } from "node:test";
import assert from "node:assert/strict";
import { event } from "../fixtures.js";
import { matchesCriteria } from "../../src/model/domain/event/eventFilters.js";

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
- Verifie les JSDoc de tout `src/` (`strictNullChecks`) puis des tests (`tests/jsconfig.json`)
- Globales (`DAYOUT_CONFIG`, `L`) declarees dans `src/types/globals.d.ts`

### Conventions de nommage

- **Variables / fonctions** : `camelCase`
- **Classes** : `PascalCase`
- **Constantes de module** : `UPPER_SNAKE_CASE` (`MAX_PAGE_LIMIT`, `LAST_PLACES_THRESHOLD`)
- **Fichiers** : camelCase sauf pour les classes exportees par defaut (ex. `EventsController.js`, `EventsView.js`)

### Conventions de langue

- Code en anglais (noms de fonctions, variables, classes, fichiers)
- **Textes affiches** et commentaires en **francais**
- Les textes affiches vivent **uniquement** dans les views

### Regles CSS

- Valeurs via les **tokens** de `tokens.css` (`--color-*`, `--space-1..7`, `--font-size-*`, `--radius-*`) : pas de valeur en dur dans les composants
- `theme-color` (meta HTML) reprend en litteral la valeur de `--color-brand` (`#241238`), a **garder synchronisee**
- **Pas de selecteur d'id** pour le style : ids reserves au JS et a l'ARIA
- **Nommage BEM leger** :
  - bloc : `.event-card`, `.site-nav`, `.card-grid`
  - element : `.event-card__meta`, `.site-header__logo` (double underscore)
  - variante : `.alert--error` (double tiret)
  - etat : `.is-*` **uniquement** si aucun attribut ARIA ne l'exprime ; sinon styler l'attribut (`[aria-current="page"]`, `[aria-busy="true"]`, `[aria-invalid="true"]`)
- **Composant rendu par une view** : feuille a cote de son JS (`src/view/components/event/eventCard/eventCard.css`, `src/view/home/pointCard/pointCard.css`) ; quand une view est decoupee, sa feuille l'est aussi
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

- Un seul `<h1>` par page
- **Skip-link** vers `#main` (lien visible au focus)
- `aria-current="page"` sur la nav
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
node --test tests/event/eventMapper.test.js             # un fichier
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

### Toutes les adresses servent `index.html`

Les pages ont des adresses sans fichier (`/events`, `/faq`, `/event?id=1`) que seul le routeur JS connait.
Ouvrir ou recharger directement `/events` demande donc au serveur de renvoyer `index.html` pour toute
adresse qui n'est pas un fichier (sinon : 404 du serveur). Exemples :

```nginx
location / { try_files $uri /index.html; }
```

```apache
# .htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.html [L]
```

```text
# Netlify / Cloudflare Pages : fichier _redirects
/*  /index.html  200
```

`php -S localhost:5500 -t front` (installation locale) le fait deja ; **Live Server ne le fait pas** :
avec lui, partir de `/` et naviguer par les liens.

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
- [ ] Toute adresse qui n'est pas un fichier renvoie `index.html` (voir ci-dessus)
- [ ] Headers `Content-Security-Policy` configures sur le serveur HTTP (recommande, a completer avec les domaines des images de couverture : `default-src 'self'; script-src 'self' https://unpkg.com; style-src 'self' https://unpkg.com https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: https://tile.openstreetmap.org https://unpkg.com <API_URL>; connect-src 'self' <API_URL>`). `unpkg.com` sert Leaflet (JS, CSS et images de ses controles), `tile.openstreetmap.org` les tuiles de la carte
- [ ] `Strict-Transport-Security` (HSTS) par le serveur HTTP
- [ ] Compression (gzip / brotli) sur `*.js`, `*.css`, `*.svg`, `*.html`
- [ ] Cache long sur `assets/img/` (immutables) ; `Cache-Control: no-cache` (revalidation a chaque chargement, via ETag) sur `*.html`, `*.css` et `src/*.js` : les modules ES ne sont pas versionnes, un cache "court" pourrait melanger des modules de deux deploiements

---

## 20) Troubleshooting

### 404 du serveur en ouvrant ou rechargeant `/events`, `/faq`...

Le serveur ne renvoie pas `index.html` pour les adresses de l'app : voir *Deploiement > Toutes les
adresses servent `index.html`*. En local, servir avec `php -S localhost:5500 -t front` (Live Server ne le
fait pas).

### "Impossible d'afficher cette page"

Le routeur n'a pas pu lire `pages/<page>.html` (voir la console : statut HTTP, ou `pas de <main id="app-root">`).
Verifier que le fichier existe et contient un `<main id="app-root">` : seul son contenu est affiche, ses
`<script>` ne sont pas executes (le JS de la page est le `mount` de `app/appRoutes.js`).

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
3. CSP du serveur trop stricte : autoriser `https://unpkg.com` dans `script-src` **et** `style-src`

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

Les filtres de la carte sont **cote client** (`model/domain/event/eventFilters.js`). Verifier :

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

### Typo (Google Fonts, `<link>` + preconnect dans chaque page)

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

### Footer

`view/layout/footer/footer.js` remplit `<footer class="site-footer" data-layout>` (logo, accroche, credit),
comme le header, avec un fallback `<noscript>` statique. **Absent de la page carte** (plein ecran). Liens
FAQ / CGU / Mentions legales / Contact a ajouter quand ces pages existeront (**pas de lien casse** d'ici la).

---

## Ajouter une page (checklist complete)

### 1. HTML

Seul le `<main id="app-root">` est copie par le routeur dans `index.html` (header, footer, polices et CSS y sont deja).

```html
<!-- pages/ma-page.html -->
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Ma page — DayOut</title>
</head>
<body>
  <main id="app-root" class="container ma-page">
    <h1>Titre unique de la page</h1>
    <p id="ma-page-status" class="status-message" role="status"></p>
    <p id="ma-page-error" class="alert alert--error" role="alert"></p>
    <div id="ma-page-content" aria-busy="false"></div>
  </main>
</body>
</html>
```

### 2. Service et mapper de requete (si besoin)

```js
// src/model/mappers/xxxRequestMapper.js : la query string (pagination via toPageQuery)
export function toXxxQuery(page = {}) {
    return toPageQuery(page);
}

// src/model/services/xxxService.js : appel + mapper de reponse, aucune URL construite a la main
export async function fetchXxx({ signal } = {}) {
    const page = await api.get(`/xxx?${toXxxQuery()}`, { signal });
    return toXxxEntries(page.items);
}
```

### 3. View (+ messages)

```js
// src/view/ma-page/MaPageView.js : seule a connaitre les ids de la page
import { maPageStatus } from "./maPageMessages.js"; // textes calcules : fonctions pures, testees sans DOM

export class MaPageView {
    constructor(root = document) { // root : le <main> de la page, passe par le point d'entree
        this.statusEl  = root.querySelector("#ma-page-status");
        this.errorEl   = root.querySelector("#ma-page-error");
        this.contentEl = root.querySelector("#ma-page-content");
    }

    get isReady() {
        return Boolean(this.statusEl && this.errorEl && this.contentEl);
    }

    showLoading() { this.contentEl.setAttribute("aria-busy", "true"); this.errorEl.textContent = ""; }
    endLoading()  { this.contentEl.setAttribute("aria-busy", "false"); }
    showItems(items) { /* h(...) + textContent, jamais innerHTML */ this.statusEl.textContent = maPageStatus(items.length); }
    showError(message) { this.statusEl.textContent = ""; this.errorEl.textContent = message; }
}
```

### 4. Controleur

```js
// src/controller/MaPageController.js
import { fetchXxx } from "../model/services/xxxService.js";
import { MaPageView } from "../view/ma-page/MaPageView.js";
import { PageLoader } from "./PageLoader.js";

export class MaPageController {
    constructor({ view = new MaPageView(), load = fetchXxx } = {}) {
        this.view = view;
        this.load = load;
    }

    async start({ signal } = {}) {
        if (!this.view.isReady || this.started || signal?.aborted) return;
        this.started = true;
        // un chargement a la fois, message d'erreur, rien apres le depart de la page
        await new PageLoader(this.view, { signal }).run((s) => this.load({ signal: s }), {
            onSuccess: (items) => this.view.showItems(items),
        });
    }
}
```

### 5. Point d'entree et route

```js
// src/pages/ma-page.js : appele par le routeur une fois pages/ma-page.html affiche
import { MaPageController } from "../controller/MaPageController.js";
import { MaPageView } from "../view/ma-page/MaPageView.js";

/** @param {import("../app/router.js").PageContext} page */
export function mountMaPage({ root, signal }) {
    return new MaPageController({ view: new MaPageView(root) }).start({ signal });
}

// src/app/appRoutes.js : une ligne, et NAV_ITEMS (view/layout/header/header.js) si la page est dans le menu
{ path: "/ma-page", file: "pages/ma-page.html", mount: mountMaPage },
```

### 6. Tests

Ajouter au moins un test pour :

- Le **mapper** si la page introduit une nouvelle ressource
- Les **regles metier** si la page introduit du calcul client
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
