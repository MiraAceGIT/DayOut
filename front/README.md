# Front — DayOut (HTML / CSS / JS vanilla)

Interface statique qui consomme l'API REST du back via `fetch()`.
Pas de framework, pas de build : on ouvre les fichiers HTML directement.

## Structure

```
front/
├── index.html              ← accueil
├── pages/
│   └── events.html         ← liste des evenements (demo API)
├── assets/
│   ├── css/
│   │   ├── reset.css
│   │   └── main.css
│   └── js/
│       ├── api.js          ← wrapper fetch() (base URL + erreurs)
│       └── events.js       ← logique de la page events
└── README.md
```

## Lancer en local

1. Demarrer le back (voir `back/README.md`) sur `http://localhost:8000`.
2. Servir le front — **ne pas ouvrir en `file://`** : les modules ES
   (`<script type="module">`) et `fetch()` cross-origin ont besoin d'un
   serveur HTTP.

   **Serveur statique PHP** (sans dependance) :
   ```bash
   php -S localhost:5500 -t front
   ```
   Puis ouvrir `http://localhost:5500`.

   **Extension VS Code "Live Server"** : clic droit sur `index.html` → *Open with Live Server*.

## Ajouter une page qui parle a l'API

```js
import { api } from "../assets/js/api.js";

const data = await api.get("/api/events");
// api.post("/api/...", { ... });
// api.put("/api/...", { ... });
// api.del("/api/...");
```

Si le back tourne sur un autre host/port, modifier `API_BASE_URL` dans
`assets/js/api.js`.
