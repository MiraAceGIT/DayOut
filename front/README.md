# Front — DayOut (HTML / CSS / JS vanilla)

Interface statique qui consomme l'API REST du back via `fetch()`.
Pas de framework, pas de build, pas de npm : modules ES natifs servis tels quels.

## Structure

```
front/
├── index.html                    ← accueil
├── pages/
│   └── events.html               ← liste des evenements (demo API)
├── assets/
│   ├── css/
│   │   ├── main.css              ← point d'entree unique (@import des feuilles ci-dessous)
│   │   ├── reset.css             ← reset minimal + prefers-reduced-motion
│   │   ├── tokens.css            ← variables : couleurs, --space-*, --font-size-*...
│   │   ├── base.css              ← styles des elements (body, liens, focus)
│   │   ├── layout.css            ← .container, structure de page
│   │   └── components/           ← une feuille par composant
│   │       ├── skip-link.css, site-header.css, hero.css
│   │       ├── card-grid.css, event-card.css
│   │       └── feedback.css (alertes / statut), form.css (erreurs de champ)
│   └── js/
│       ├── config.local.example.js  ← modele de surcharge locale (a copier)
│       ├── config.local.js          ← surcharge locale optionnelle (ignore par git)
│       ├── core/
│       │   ├── config.js         ← configuration unique (API_BASE_URL, racine du front)
│       │   ├── api.js            ← wrapper fetch() : enveloppe, erreurs, CSRF, 401, timeout
│       │   ├── layout.js         ← renderHeader({ current }) : header + nav partages
│       │   └── boot.js           ← boot({ page }) : layout + handler 401
│       ├── lib/
│       │   ├── dom.js            ← h(tag, props, ...children) : DOM sans innerHTML
│       │   ├── format.js         ← formatDate, formatPrice
│       │   └── forms.js          ← showFieldErrors, clearFieldErrors, messageFor
│       └── pages/
│           ├── home.js           ← script de index.html
│           └── events.js         ← script de pages/events.html
├── .gitignore
└── README.md
```

### Regle de dependances

- `pages/*` importent `core/` et `lib/`, **jamais l'inverse** : ni `core/` ni `lib/`
  n'importent un fichier de `pages/`.
- `core/` peut utiliser `lib/` ; `lib/` ne depend de rien (utilitaires purs).
- Un fichier de `pages/` par page HTML, qui appelle `boot({ page })` **en premier**.

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

Une seule source : `assets/js/core/config.js` (`API_BASE_URL`, defaut `http://localhost:8000`).

Pour pointer vers une autre API **sans modifier le code** :

```bash
cp front/assets/js/config.local.example.js front/assets/js/config.local.js
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
<script src="../assets/js/config.local.js"></script>
<script type="module" src="../assets/js/pages/ma-page.js"></script>
```

```js
// assets/js/pages/ma-page.js
import { boot } from "../core/boot.js";
import { api } from "../core/api.js";
import { h } from "../lib/dom.js";
import { messageFor } from "../lib/forms.js";

boot({ page: "ma-page" });          // ajouter l'entree dans NAV_ITEMS (core/layout.js) si besoin

const event = await api.get(`/api/events/${encodeURIComponent(id)}`);
// api.post(path, body, { signal, timeout }) ; api.put / api.patch / api.del
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
  `boot()` l'installe pour rediriger vers `pages/login.html?next=...` (`LOGIN_PATH` dans `core/boot.js`).
- Timeout par defaut 10 s (`timeout: 0` pour le desactiver), combinable avec un `signal` appelant.

Formulaires : sur `err.isValidation`, `showFieldErrors(form, err.details)` pose
`aria-invalid` et un `<p id="<champ>-error" class="field-error">` relie par
`aria-describedby` ; `clearFieldErrors(form)` les retire. Afficher `messageFor(err)`
dans l'alerte de la page (message utilisateur, avec la reference de requete).

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
- Un composant = une feuille dans `components/`, ajoutee par `@import` dans `main.css`.
