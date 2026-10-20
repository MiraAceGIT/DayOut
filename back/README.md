# Back — API REST DayOut (PHP + MariaDB)

API REST en PHP vanilla (pas de framework ni Composer). Architecture MVC :
`Models` lisent la BDD, `Controllers` orchestrent, `Core` fournit routeur /
BDD / request / response / password.

## Structure

```
back/
├── public/
│   ├── index.php       ← point d'entree unique (front controller)
│   └── .htaccess       ← rewrite + headers de securite
├── src/
│   ├── Core/           ← Router, Database, Request, Response, Controller, Password
│   ├── Controllers/    ← logique HTTP (une classe par ressource)
│   └── Models/         ← acces BDD (requetes SQL)
├── config/config.php   ← charge le .env
├── routes/api.php      ← table de routage
├── .env.example
└── README.md
```

## Lancer l'API en local

1. Copier `.env.example` en `.env` et adapter si besoin.
2. S'assurer que la base `dayout` est importee (voir `infra/README.md`).
3. Au choix :

   **A. Serveur PHP integre (recommande en dev)** — depuis la racine du projet :
   ```bash
   php -S localhost:8000 -t back/public
   ```
   L'API ecoute sur `http://localhost:8000`.

   **B. XAMPP Apache** — placer le projet dans `C:\xampp\htdocs\dayout`
   puis ouvrir `http://localhost/dayout/back/public/`.

## Format de reponse

Toutes les reponses sont en JSON et suivent une enveloppe standard.

**Succes** (`2xx`) :
```json
{ "data": { ... } }
```

**Erreur** (`4xx`, `5xx`) :
```json
{ "error": { "code": "NOT_FOUND", "message": "Event not found", "details": { "id": 42 } } }
```

Codes d'erreur emis : `BAD_REQUEST`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`,
`INTERNAL_ERROR`, `ENCODING_ERROR`.

## Endpoints

| Methode | Chemin               | Query params                                   | 200 — shape (sous `data`)             | Erreurs                                   |
| ------- | -------------------- | ---------------------------------------------- | ------------------------------------- | ----------------------------------------- |
| GET     | `/api/health`        | —                                              | `{ api, db, time }`                   | —                                         |
| GET     | `/api/events`        | `limit` (1-100, def. 20), `offset` (def. 0)    | `{ items: Event[], limit, offset }`   | —                                         |
| GET     | `/api/events/{id}`   | — (`id` entier obligatoire via regex `\d+`)    | `Event`                               | `404 NOT_FOUND`, `404` si `{id}` non num. |

**Shape `Event`** (contrat JSON stable) :
```json
{
  "id": 1,
  "creator_id": 2,
  "title": "...",
  "slug": "...",
  "description": "...",
  "cover_image_url": null,
  "start_at": "2026-11-07T20:00:00+00:00",
  "end_at":   "2026-11-08T02:00:00+00:00",
  "venue_name": "...",
  "address_line": "...",
  "city": "Paris",
  "postal_code": "75002",
  "capacity": 250,
  "registered_count": 12,
  "price_cents": 1500,
  "currency": "EUR",
  "is_paid": true,
  "external_ticket_url": null,
  "school_restricted": true,
  "restricted_school_id": 1,
  "status": "published",
  "published_at": "2026-10-01T09:00:00+00:00",
  "created_at":   "2026-10-01T09:00:00+00:00"
}
```

Types garantis : `is_paid` et `school_restricted` en `bool`, entiers en `int`,
dates en ISO 8601 UTC (`DateTime::ATOM`). Seuls les events `status='published'`
sont exposes.

## Tester

```bash
curl http://localhost:8000/api/health
curl http://localhost:8000/api/events
curl 'http://localhost:8000/api/events?limit=5&offset=0'
curl http://localhost:8000/api/events/1
curl http://localhost:8000/api/events/9999   # 404 NOT_FOUND
curl http://localhost:8000/api/events/abc    # 404 NOT_FOUND (regex)
```

## Ajouter une route

1. Ecrire le `Model` (requetes SQL) dans `src/Models/`.
2. Ecrire le `Controller` dans `src/Controllers/` (hereite de `App\Core\Controller`).
3. Declarer la route dans `routes/api.php` :
   ```php
   $router->get('/api/users/{id:\d+}', [UserController::class, 'show']);
   ```
4. Pour une route protegee (futur) :
   ```php
   $router->post('/api/events', [EventController::class, 'create'], [AuthMiddleware::class]);
   ```

## Securite — ce qui est en place

- PDO `ERRMODE_EXCEPTION` + `EMULATE_PREPARES=false` + bindings nommes (anti-SQLi)
- CORS whitelist via `CORS_ORIGIN` (plus `*` avec credentials)
- Headers : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, `CSP` (API-only)
- `.htaccess` bloque les dotfiles et extensions sensibles (`.env`, `.sql`, …)
- Variables d'env requises validees au boot (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PORT`)
- Jamais de `$e->getMessage()` ou de trace dans la reponse HTTP (log serveur uniquement)
- `App\Core\Password` : bcrypt cost 12, pret pour l'auth future

## Flux d'une requete

```
HTTP -> public/.htaccess -> public/index.php
     -> autoload + CORS + headers securite
     -> routes/api.php (declarations)
     -> Router::dispatch (match {param}, middlewares)
     -> Controller::action
     -> Model::query  -> PDO -> MariaDB
     -> Response::json / Response::error
```
