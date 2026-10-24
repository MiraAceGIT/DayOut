# Back — API REST DayOut (PHP + MariaDB)

API REST en PHP vanilla (pas de framework ni Composer). Architecture MVC :
`Models` decrivent les tables et exposent l'acces aux donnees par methodes
nommees, `Controllers` orchestrent, la vue est la reponse JSON (`Response`).
`Core` fournit routeur / BDD / request / response / password et l'ORM maison
(`Core/Orm`).

## Structure

```
back/
├── public/
│   ├── index.php       ← point d'entree unique (front controller)
│   └── .htaccess       ← rewrite + headers de securite
├── src/
│   ├── Core/           ← Router, Database, Request, Response, Controller, Password
│   │   ├── Log/        ← journalisation (error / info / audit)
│   │   └── Orm/        ← moteur ORM maison
│   │       ├── Connection.php, ValueBinder.php  ← execution PDO + bindings
│   │       ├── Caster.php, Rows.php             ← casts + batch anti N+1
│   │       └── Query/  ← QueryBuilder (+ traits), WhereClause, QueryParts,
│   │                     Compiler, Grammar
│   ├── Models/         ← M : Model.php (base) + une classe par table
│   └── Controllers/    ← C : logique HTTP (une classe par ressource)
├── config/config.php   ← charge le .env
├── routes/            ← un fichier de routes par ressource (charges par api.php)
├── storage/logs/       ← fichiers de logs (crees a la volee, ignores par git)
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

1. Ecrire le `Model` dans `src/Models/` (herite de `App\Models\Model`, voir *ORM* ci-dessous).
2. Ecrire le `Controller` dans `src/Controllers/` (herite de `App\Core\Controller`) ;
   il instancie ses models dans son constructeur (`new User()`).
3. Declarer les routes dans `routes/<ressource>.php` (ex. `routes/users.php`),
   charge automatiquement par `routes/api.php` :
   ```php
   <?php
   declare(strict_types=1);

   use App\Controllers\UserController;

   /** @var \App\Core\Router $router */
   $router->get('/api/users/{id:\d+}', [UserController::class, 'show']);
   ```
4. Pour une route protegee (futur) :
   ```php
   $router->post('/api/events', [EventController::class, 'create'], [AuthMiddleware::class]);
   ```

## ORM

ORM maison minimal : le moteur est dans `src/Core/Orm/`, les models dans
`src/Models/`.

| Classe                  | Role                                                                  |
| ----------------------- | --------------------------------------------------------------------- |
| `Models\Model`          | base des models : definition de table (constantes), `find`, `create`... |
| `Query\QueryBuilder`    | requetes fluentes (`where`, `join`, `orderBy`, `get`, `count`...)     |
| `Query\Compiler`        | assemble le SQL ; `Query\Grammar` valide et quote les identifiants    |
| `Connection`            | execute le SQL via PDO (bindings, transactions)                       |
| `Caster`                | casts des lignes lues (`int`, `bool`, `datetime`...)                  |
| `Rows`                  | `keyBy` / `groupBy` pour assembler des chargements batch              |

Les lignes sont des tableaux associatifs deja castes, directement passables
a `$this->ok()`. Un model construit sans argument utilise la `Connection`
partagee (`Database::orm()`) ; on peut lui en passer une autre pour les tests.

**Declarer un model :**
```php
// src/Models/User.php
final class User extends Model
{
    protected const TABLE    = 'users';
    protected const COLUMNS  = ['id', 'email', 'first_name', 'last_name', 'role', 'banned', 'created_at'];  // jamais password_hash
    protected const FILLABLE = ['email', 'first_name', 'last_name'];  // saisie client uniquement
    protected const CASTS    = ['banned' => 'bool', 'created_at' => 'datetime'];  // colonnes presentes dans COLUMNS

    /** Une methode par besoin metier ; query() est protected. */
    public function listAdmins(): array
    {
        return $this->query()->where('role', 'admin')->orderBy('last_name')->get();
    }
}

// src/Controllers/UserController.php
public function __construct(?User $users = null)
{
    $this->users = $users ?? new User();
}
```

| Constante     | Role                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `TABLE`       | nom de la table (obligatoire)                                        |
| `PRIMARY_KEY` | `id` par defaut                                                      |
| `COLUMNS`     | colonnes lues par defaut (contrat API) — `*` par defaut              |
| `FILLABLE`    | colonnes de la saisie client acceptees par `create()` / `updateById()`, le reste est ignore |
| `CASTS`       | `int`, `float`, `bool`, `datetime` (ISO 8601 UTC), `json`            |

**Lire** (methodes publiques heritees + `$this->query()` dans le model) :
```php
$users->find(3);                                 // ?array
$users->findMany([1, 2, 2, 3]);                  // [1 => [...], 2 => [...], 3 => [...]] (1 requete)
$users->all();

// dans le model User :
$this->query()->where('school_id', 1)->orderBy('last_name')->limit(20)->get();
$this->query()->where('reliability_score', '<', 40)->count();
$this->query()->whereIn('id', [1, 2, 3])->pluck('email');
$this->query()
    ->where(fn ($q) => $q->where('role', 'admin')->orWhere('banned', true))  // ( ... OR ... )
    ->whereNotNull('last_login_at')
    ->forPage(2, 20)                             // LIMIT 20 OFFSET 20
    ->get();
$this->query()->where('role', 'admin')->exists();
```

**Ecrire :**
```php
// 1er argument : saisie client (filtree par FILLABLE)
// 2e argument  : champs fixes par le serveur (non filtres)
$id = $users->create($this->body(), ['password_hash' => Password::hash($password)]);  // id insere
$users->updateById($id, ['first_name' => 'B']);                                       // nb lignes
$users->deleteById($id);

// Requete libre (pas de filtre FILLABLE ni de casts) : $this->table() dans un
// model, ou Database::orm()->table(...) ailleurs.
$this->table()->where('event_id', 4)->where('status', 'no_show')->update(['status' => 'cancelled']);

// Transaction : la Connection est partagee, donc tous les models construits
// sans argument participent a la meme transaction (pas d'imbrication).
Database::orm()->transaction(function (Connection $db) use ($events, $registrations) {
    // tout ou rien ; la valeur retournee est renvoyee par transaction()
});
```

**Jointures / agregats** (`$this->table()` : builder brut, sans COLUMNS ni casts) :
```php
$this->table()
    ->select('events.id', 'events.title', 'u.email AS creator_email')
    ->selectRaw('COUNT(r.id) AS nb_registrations')
    ->join('users AS u', 'u.id', '=', 'events.creator_id')
    ->leftJoin('registrations AS r', 'r.event_id', '=', 'events.id')
    ->groupBy('events.id', 'events.title', 'u.email')
    ->orderByDesc('nb_registrations')
    ->get();
```
`->toSql()` / `->getBindings()` affichent la requete generee pour debugger.

**Regle anti N+1 :** ne jamais appeler `find()` ou une requete dans une boucle.
Charger les relations en une requete (`findMany` ou `whereIn`), puis les
rattacher en PHP avec `Rows::keyBy` / `Rows::groupBy` :
```php
// Model Registration
public function listForEvents(array $eventIds): array
{
    if ($eventIds === []) {
        return [];
    }
    $rows = $this->query()->whereIn('event_id', $eventIds)->get();
    return Rows::groupBy($rows, 'event_id');      // [event_id => [registration, ...]]
}

// Controller : 2 requetes au total, quel que soit le nombre d'events
$items  = $this->events->listPublished($limit, $offset);
$byEvent = $this->registrations->listForEvents(array_column($items, 'id'));
foreach ($items as &$event) {
    $event['registrations'] = $byEvent[$event['id']] ?? [];
}
unset($event);
```

Garde-fous : valeurs toujours bindees (`?`), noms de colonnes valides par regex
au moment de l'appel et entoures de backticks, operateurs, sens de tri et types
de jointure en whitelist, `update()` / `delete()` refuses sans `WHERE`.
`selectRaw()` / `whereRaw()` acceptent du SQL brut : n'y concatener **jamais**
une entree utilisateur (passer par les bindings de `whereRaw`). Les erreurs SQL
(contraintes, triggers `SIGNAL 45000`) remontent en `PDOException`.

## Journalisation

Logger maison sans dependance (`src/Core/Log/`), point d'entree unique `App\Core\Log\Log`.

| Fichier                         | Contenu                                                                   |
| ------------------------------- | ------------------------------------------------------------------------- |
| `error-AAAA-MM-JJ.log`          | `warning` et plus grave : exceptions, erreurs PHP, fatales                 |
| `info-AAAA-MM-JJ.log`           | `notice`, `info`, `debug` : une ligne par requete HTTP (statut, duree)     |
| `audit-AAAA-MM-JJ.log`          | actions metier sensibles, ecrites quel que soit `LOG_LEVEL`               |

Rotation quotidienne (UTC) ; les fichiers plus vieux que `LOG_RETENTION_DAYS`
(30 j) ou `AUDIT_RETENTION_DAYS` (365 j) sont supprimes a la premiere ecriture
du jour. Dossier : `LOG_PATH` (defaut `back/storage/logs`).

**Format** : une ligne JSON par entree.
```json
{"timestamp":"2026-10-24T09:12:03.481Z","level":"error","channel":"error","message":"Database error","requestId":"9f2c4e1a7b3d5f60","userId":null,"method":"GET","path":"/api/events","context":{"exception":{"class":"PDOException","message":"...","code":"42S22","file":"...:42","trace":["..."]}}}
```
`requestId` est commun a toutes les entrees d'une requete et renvoye au client
dans l'en-tete `X-Request-Id` : le citer suffit pour retrouver un incident.

**Utilisation :**
```php
use App\Core\Log\Log;

Log::error('Payment provider unreachable', ['exception' => $e, 'eventId' => $id]);
Log::info('Reminder emails sent', ['count' => $sent]);

// Audit : action, type de ressource, id, resultat (success | failure | denied)
Log::audit('event.delete', 'event', $id);
Log::audit('auth.login', 'user', $userId, 'failure', ['reason' => 'bad_password']);

// Dans le futur middleware d'auth, une fois l'utilisateur identifie :
Log::setUserId($user['id']);
```

Garde-fous :
- les cles de contexte sensibles (`password`, `token`, `secret`, `authorization`,
  `cookie`, `csrf`, `session`, `api_key`...) sont remplacees par `[REDACTED]`,
  et la valeur de `DB_PASSWORD` est masquee partout ;
- traces d'exception sans les arguments des fonctions, chemin sans query string ;
- l'IP n'est enregistree que dans l'audit ;
- une erreur d'ecriture des logs ne casse jamais la requete (repli sur le log PHP).

## Securite — ce qui est en place

- PDO `ERRMODE_EXCEPTION` + `EMULATE_PREPARES=false` + placeholders `?` bindes (anti-SQLi)
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
     -> routes/api.php -> routes/<ressource>.php (declarations)
     -> Router::dispatch (match {param}, middlewares)
     -> Controller::action
     -> Model (methode nommee)
     -> QueryBuilder -> Compiler (SQL) -> Connection (bindings) -> PDO -> MariaDB
     -> Caster (casts de chaque ligne, d'apres Model::CASTS)
     -> Response::json / Response::error
```
