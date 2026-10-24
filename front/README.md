# Front — DayOut (HTML / CSS / JS vanilla)

Interface statique qui consomme l'API REST du back via `fetch()`.
Pas de framework, pas de build, pas de npm : modules ES natifs servis tels quels.

## Structure

```
front/
├── index.html                    ← accueil
├── pages/
│   └── events.html               ← liste des evenements (demo API)
├── assets/css/                  ← CSS global (sans JS proprietaire)
│   ├── main.css                  ← point d'entree unique (@import de toutes les feuilles)
│   ├── reset.css                 ← reset minimal + prefers-reduced-motion
│   ├── tokens.css                ← variables : couleurs, --space-*, --font-size-*...
│   ├── base.css                  ← styles des elements (body, liens, focus)
│   ├── layout.css                ← .container, structure de page
│   └── components/               ← composants purement HTML/CSS
│       ├── skip-link.css, hero.css, card-grid.css
│       └── feedback.css (alertes / statut), form.css (erreurs de champ)
├── src/                          ← code de l'application
│   ├── config.local.example.js   ← modele de surcharge locale (a copier)
│   ├── config.local.js           ← surcharge locale optionnelle (ignore par git)
│   ├── core/
│   │   ├── config.js             ← configuration unique (API_BASE_URL, racine du front)
│   │   ├── api.js                ← wrapper fetch() : enveloppe, erreurs, CSRF, 401, timeout
│   │   └── errors.js             ← messageFor(err), isApiError(err) : messages utilisateur
│   ├── app/
│   │   └── boot.js               ← boot({ page }) : header + handler 401 (LOGIN_PATH)
│   ├── lib/
│   │   ├── dom.js                ← h(tag, props, ...children) : DOM sans innerHTML
│   │   ├── format.js             ← formatDate, formatPrice
│   │   └── forms.js              ← showFieldErrors, clearFieldErrors
│   ├── models/
│   │   └── Event.js              ← fetchUpcomingEvents({ limit, signal }), toEvent(raw)
│   ├── views/                    ← un dossier par composant : son JS + sa feuille CSS
│   │   ├── layout/header/
│   │   │   ├── header.js         ← renderHeader({ current }), NAV_ITEMS : header + nav partages
│   │   │   └── header.css
│   │   ├── components/eventCard/
│   │   │   ├── eventCard.js      ← renderEventCard(event) : evenement -> Node
│   │   │   └── eventCard.css
│   │   └── EventsView.js         ← #events-list / -error / -status : showLoading, showEvents, showError
│   ├── controllers/
│   │   └── EventsController.js   ← orchestre model + view (chargement, erreurs)
│   └── pages/
│       ├── home.js               ← point d'entree de index.html
│       └── events.js             ← point d'entree de pages/events.html
├── .gitignore
└── README.md
```

### Architecture MVC

- **models/** : acces cote client a l'API REST (appels via `core/api.js`) et normalisation
  des reponses (snake_case -> camelCase). Aucun DOM. A ne pas confondre avec
  `back/src/Models` (acces base de donnees cote serveur).
- **views/** : construisent et mettent a jour le DOM (`h()`, `textContent`). Aucun appel API,
  aucune regle metier : elles recoivent des donnees deja pretes (objets normalises, messages).
  Chaque composant a son dossier, avec son JS et sa feuille CSS cote a cote.
- **controllers/** : orchestrent model + view (chargement, succes, erreur). Ne touchent
  jamais le DOM directement.
- **pages/** : points d'entree minces, un par page HTML : `boot({ page })` **en premier**,
  puis demarrage du controleur.
- Fonctions pour ce qui est sans etat (models, composants) ; petite classe pour ce qui garde
  des references (une view et ses elements, le controleur qui la pilote).

### Regle de dependances

```
pages/        -> app/boot.js, controllers/
controllers/  -> models/, views/, core/errors.js
models/       -> core/api.js
views/        -> views/components/, lib/, core/config.js (frontUrl)
app/boot.js   -> core/, views/layout/header/
core/         -> core/ uniquement
lib/          -> rien (utilitaires purs)
```

- Rien n'importe `pages/` ; pas de dependance circulaire.
- Une view n'importe ni `models/` ni `core/api.js` ; un model n'importe ni `views/` ni `lib/dom.js`.

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
<script src="../src/config.local.js"></script>
<script type="module" src="../src/pages/ma-page.js"></script>
```

```js
// src/models/Event.js : acces API + normalisation, sans DOM
export async function fetchEvent(id, { signal } = {}) {
  return toEvent(await api.get(`/api/events/${encodeURIComponent(id)}`, { signal }));
}
// api.post(path, body, { signal, timeout }) ; api.put / api.patch / api.del
```

```js
// src/views/MaPageView.js : seul fichier qui connait les ids de la page
import { h } from "../lib/dom.js";

export class MaPageView {
  constructor(root = document) {
    this.errorEl  = root.querySelector("#ma-page-error");
    this.statusEl = root.querySelector("#ma-page-status");
  }

  get isReady() {
    return Boolean(this.errorEl && this.statusEl);
  }

  showEvent(event) { /* h(...) + textContent, jamais innerHTML */ }

  showError(message) {
    this.statusEl.textContent = "";
    this.errorEl.textContent = message;
  }
}
```

```js
// src/controllers/MaPageController.js : orchestre, ne touche pas le DOM
import { isApiError, messageFor } from "../core/errors.js";
import { fetchEvent } from "../models/Event.js";
import { MaPageView } from "../views/MaPageView.js";

export class MaPageController {
  constructor({ view = new MaPageView() } = {}) {
    this.view = view;
  }

  async start(id) {
    if (!this.view.isReady) return;
    if (!id) {
      this.view.showError("Aucun evenement selectionne.");
      return;
    }
    try {
      this.view.showEvent(await fetchEvent(id));
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
import { MaPageController } from "../controllers/MaPageController.js";

boot({ page: "ma-page" });          // ajouter l'entree dans NAV_ITEMS (views/layout/header/header.js) si besoin

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
  `boot()` l'installe pour rediriger vers `pages/login.html?next=...` (`LOGIN_PATH` dans `app/boot.js`).
- Timeout par defaut 10 s (`timeout: 0` pour le desactiver), combinable avec un `signal` appelant.

Formulaires : sur `err.isValidation`, `showFieldErrors(form, err.details)` pose
`aria-invalid` et un `<p id="<champ>-error" class="field-error">` relie par
`aria-describedby` ; `clearFieldErrors(form)` les retire (`lib/forms.js`). Afficher
`messageFor(err)` (`core/errors.js`) dans l'alerte de la page (message utilisateur, avec la
reference de requete) ; c'est le controleur qui le calcule et le passe a la view.

## Regles de securite

- **Jamais `innerHTML` (ni `outerHTML`, `insertAdjacentHTML`) avec des donnees.**
  Construire le DOM avec `h()` (`lib/dom.js`) ou `textContent`. `h()` refuse la prop
  `innerHTML` et n'accepte que des URL http(s) ou relatives pour `href` / `src`.
- **Construire les URL avec `encodeURIComponent`** pour chaque segment variable
  (`/api/events/${encodeURIComponent(id)}`) ou `URLSearchParams` pour la query string.

## Accessibilite

- Un seul `<h1>` par page, skip-link vers `#main`, `aria-current="page"` sur la nav.
- Messages dynamiques : elements **permanents** dans le HTML dont on change le `textContent`.
  `role="status"` pour l'information (chargement, resultat), `role="alert"` seul pour les erreurs
  (ne pas combiner avec `aria-live`). Ne pas les masquer en `display:none`.
- Liens du contenu soulignes ; focus visible sur liens, boutons et champs ;
  `prefers-reduced-motion` respecte.

## Conventions CSS

- Valeurs via les tokens de `tokens.css` (`--color-*`, `--space-1..6`, `--font-size-*`) :
  pas de valeur en dur dans les composants. `theme-color` (meta HTML) reprend en litteral
  la valeur de `--color-primary`, a garder synchronisee.
- Pas de selecteur d'id pour le style : ids reserves au JS et a l'ARIA.
- Nommage BEM leger :
  - bloc : `.event-card`, `.site-nav`, `.card-grid`
  - element : `.event-card__meta`, `.site-header__logo` (double underscore)
  - variante : `.alert--error` (double tiret)
  - etat : `.is-*` (ex. `.is-open`) **uniquement** si aucun attribut ARIA ne l'exprime ;
    sinon styler l'attribut (`[aria-current="page"]`, `[aria-busy="true"]`, `[aria-invalid="true"]`).
- Composant rendu par une view : sa feuille vit a cote de son JS
  (`src/views/components/eventCard/eventCard.css`) ; composant purement HTML/CSS : feuille dans
  `assets/css/components/`. Dans les deux cas, ajoutee par `@import` dans `assets/css/main.css`.
