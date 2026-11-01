# Back — API REST DayOut (PHP + MariaDB)

API REST en PHP vanilla (pas de framework ni Composer). Architecture MVC, un dossier par couche :
`Model/` decrit les tables et expose l'acces aux donnees par methodes
nommees (DAO, `Model/Dao`), `Controller/` orchestre, la vue est la reponse JSON (`Response`, pas de dossier dedie).
Ce qui sort dans le JSON est fixe par les DTO (`Model/Dto`, noms publics, champs exposes) ;
les mappers (`Model/Mappers`) passent d'une ligne du Model a un DTO (`toDto`, `toDtos` pour une liste) et inversement (`toRow`).
`Core` fournit routeur / BDD / request / response / password et l'ORM maison
(`Core/Orm`).

## Structure

```
back/
├── public/
│   ├── index.php       ← point d'entree unique (front controller)
│   └── .htaccess       ← rewrite + blocage des fichiers sensibles
├── src/
│   ├── Core/           ← Config, Router, Request, Response, Controller, Database,
│   │                     Validator, HttpException, Password, Yaml (lecteur YAML minimal)
│   │   ├── Http/       ← Kernel, Middleware, ExceptionRenderer, DbErrorMapper,
│   │   │                 Cors, SecurityHeaders
│   │   ├── Log/        ← journalisation (error / info / audit) + filets ErrorHandler
│   │   └── Orm/        ← moteur ORM maison
│   │       ├── Connection.php, ValueBinder.php  ← execution PDO + bindings
│   │       ├── Caster.php, Rows.php             ← casts + batch anti N+1
│   │       └── Query/  ← QueryBuilder (+ traits), WhereClause, QueryParts,
│   │                     Compiler, Grammar
│   ├── Model/          ← M
│   │   ├── Dao/        ← acces BDD : Model.php (base) + une classe par table
│   │   ├── Services/   ← regles metier : appellent les DAO, levent les exceptions metier
│   │   ├── Exceptions/ ← BusinessException (base), ErrorKind, une classe par ressource
│   │   ├── Dto/        ← contrat JSON : un objet immuable par ressource exposee (jsonSerialize)
│   │   └── Mappers/    ← ligne du Model <-> DTO (toDto / toDtos / toRow)
│   ├── Docs/           ← OpenApiSpec : assemble docs/openapi/*.yaml
│   └── Controller/     ← C : logique HTTP (une classe par ressource)
├── config/config.php   ← charge le .env, construit et renvoie la Config
├── routes/            ← un fichier de routes par ressource (charges par api.php)
├── docs/openapi/       ← spec OpenAPI 3 en YAML : openapi.yaml (commun) + un fichier par controller
├── storage/logs/       ← fichiers de logs (crees a la volee, ignores par git)
├── tests/              ← runner maison : Unit/ (sans BDD), Integration/ (HTTP + BDD), meme arborescence que src/
├── autoload.php        ← autoload PSR-4 App\ -> src/
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
   puis ouvrir `http://localhost/dayout/back/public/health`. Le prefixe
   `/dayout/back/public` est retire automatiquement du chemin (deduit de `SCRIPT_NAME`).

Au demarrage, `config/config.php` valide le `.env` : une variable `DB_*` manquante,
un `LOG_LEVEL` ou un `APP_TIMEZONE` inconnu, un entier ou un booleen invalide (`APP_DEBUG`,
`DOCS_ENABLED` : `true`/`false`, `1`/`0`, `yes`/`no`, `on`/`off`) donnent un `500` journalise
(cause dans `error.log`). Dans le `.env`, `# ...` en fin de ligne est un commentaire, sauf dans
une valeur entre guillemets (`"a#b"`).

`APP_DEBUG=true` journalise aussi notices et deprecations, mais PHP n'affiche jamais ses erreurs
dans la reponse (elles casseraient le JSON) : tout est dans `error-AAAA-MM-JJ.log`.

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
`VALIDATION_ERROR` (422, `details` = champ -> message), `CONFLICT`, les codes metier
des services (`EVENT_NOT_FOUND`... voir *Gestion des erreurs*) et des triggers (`EVENT_FULL`, `USER_BANNED`... voir *Gestion des erreurs*),
`INTERNAL_ERROR`, `ENCODING_ERROR`.

## Endpoints

| Methode | Chemin               | Query params                                   | 200 — shape (sous `data`)             | Erreurs                                   |
| ------- | -------------------- | ---------------------------------------------- | ------------------------------------- | ----------------------------------------- |
| GET     | `/health`            | —                                              | `{ api, db, time }`                   | —                                         |
| GET     | `/events`            | `limit` (1-100, def. 20), `offset` (0-10000, def. 0), `from` (ISO 8601, def. maintenant) | `{ items: Event[], limit, offset }`   | `422 VALIDATION_ERROR` (param invalide ou hors bornes) |
| GET     | `/events/{id}`       | — (`id` entier obligatoire via regex `\d+`)    | `Event`                               | `404 EVENT_NOT_FOUND`, `404 NOT_FOUND` si `{id}` non num. |
| GET     | `/docs`              | —                                              | page HTML Swagger UI (hors enveloppe) | `404` si `DOCS_ENABLED` n'est pas vrai     |
| GET     | `/openapi.json`      | —                                              | spec OpenAPI 3 brute (hors enveloppe) | `404` si `DOCS_ENABLED` n'est pas vrai     |

`/events` ne renvoie que les events pas encore termines a `from` (`end_at > from` : ceux en cours
restent listes), tries par `start_at` puis `id`. Ecrire `from` avec `Z` (`2026-11-01T00:00:00Z`) ou
encoder le `+` d'un decalage (`%2B01:00`) : dans une query string, `+` se lit comme un espace.
Un `offset` au-dela de la fin donne une page vide, jamais la premiere page.

### Documentation interactive (Swagger)

Servie seulement avec `DOCS_ENABLED=true` dans le `.env` (coupee par defaut, donc en prod :
`routes/docs.php` n'est charge que dans ce cas par `public/index.php`). Ouvrir `http://localhost:8000/docs` (sous XAMPP : `.../dayout/back/public/docs`) :
toutes les routes, leurs parametres et les schemas (`Event`, `Category`, `Error`...), avec
**Try it out** pour appeler l'API depuis la page.

**Authorize** (en haut a droite) : saisir un token une fois, il est envoye en
`Authorization: Bearer <token>` a chaque **Try it out** et garde au rechargement de la page
(`persistAuthorization`, stocke dans le navigateur). Declare dans `openapi.yaml`
(`securitySchemes.bearerAuth`) et facultatif partout (`security: [{}, bearerAuth]`) ; une route
protegee le rendra obligatoire avec `security: [{ bearerAuth: [] }]` dans son operation.

La source est en YAML dans `docs/openapi/`, un fichier par controller (meme nom que son fichier
de `routes/`) :

```
docs/openapi/
├── openapi.yaml   ← en-tete (info, servers) + composants communs (Error, ValidationError, NotFound, InternalError)
├── events.yaml    ← EventController : tags, paths /events*, schemas Event, Category, EventPage
└── health.yaml    ← HealthController : /health, schema Health
```

Un fichier de controller ne contient que `tags`, `paths` et `components` ; `App\Docs\OpenApiSpec`
les fusionne dans `openapi.yaml` (une route ou un composant defini deux fois est une erreur) et
`/openapi.json` sert le resultat en JSON, relu a chaque appel. Un `$ref` vers un composant
d'un autre fichier marche (`#/components/schemas/Error`) : apres fusion, tout est dans le meme document.

Le YAML est lu par `App\Core\Yaml` (pas de Composer ni d'extension `yaml`), qui ne prend qu'un
sous-ensemble : mappings / listes en blocs, `[a, b]` et `{ k: v }` sur une ligne, scalaires simples
ou entre guillemets, blocs `|` et `>`, commentaires. Refuses avec fichier et ligne : ancres / alias,
tags `!`, tabulations, scalaire simple sur plusieurs lignes (utiliser `>-`). Mettre entre guillemets
une valeur qui commence par `` ` ``, `@`, `*`, `&`, `!` ou qui contient `: ` ou ` #`.

`tests/Unit/Docs/OpenApiContractTest.php` echoue si la spec derive du code : route declaree mais non documentee
(ou l'inverse), fichier de `routes/` sans son YAML, champs du schema `Event` / `Category`
differents de `EventDto` / `CategoryDto`.

Swagger UI est charge depuis jsDelivr en version epinglee avec SRI (`DocsController`) : pour
monter de version, changer l'URL et recalculer les deux hashes
(`openssl dgst -sha384 -binary fichier | openssl base64 -A`). La page a sa propre CSP
(scripts du CDN + script d'init autorise par son hash) ; toutes les autres reponses gardent
`default-src 'none'`.

**Shape `Event`** (contrat JSON stable, fixe par `App\Model\Dto\EventDto`) :
```json
{
  "id": 1,
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
  "latitude": 48.8689,
  "longitude": 2.341,
  "capacity": 250,
  "registered_count": 12,
  "price_cents": 1500,
  "currency": "EUR",
  "is_paid": true,
  "external_ticket_url": null,
  "school_restricted": true,
  "categories": [
    { "slug": "bde",    "label": "BDE / Assoc" },
    { "slug": "soiree", "label": "Soiree" }
  ]
}
```

Types garantis : `is_paid` et `school_restricted` en `bool`, entiers en `int`,
`latitude` / `longitude` en `float` ou `null` (non geolocalise), dates en ISO 8601
UTC (`DateTime::ATOM`). `categories` : liste de `{ slug, label }` triee par `label`
puis `id`, categories actives uniquement, `[]` si aucune ; chargees en une seule
requete pour toute la page (`Category::listActiveByEvent`). Seuls les events
`status='published'` sont exposes (et, dans la liste, pas encore termines). Colonnes lues mais jamais exposees :
`creator_id`, `restricted_school_id`, `status`, `published_at`, `created_at`.

## Tester

### Tests automatiques

Runner maison sans dependance (pas de Composer), depuis Git Bash :

```bash
back/tests/run.sh                 # tout : unitaires + integration
back/tests/run.sh EventMapperTest # filtre sur "Dossier/Classe::methode"
back/tests/run.sh Model/          # tous les tests d'un dossier
back/tests/run.sh --unit          # unitaires seuls, sans BDD
back/tests/run.sh --integration   # integration seule (remet dayout_test a zero)
```

Les tests suivent l'arborescence de `src/` : le test de `src/Core/Http/Kernel.php` est
`tests/Unit/Core/Http/KernelTest.php` (namespace `Tests\Unit\Core\Http`), un fichier par classe testee.

```
tests/
├── Unit/                       ← une classe testee directement, sans serveur ni MariaDB
│   ├── Controller/             ← DocsControllerTest
│   ├── Core/                   ← ConfigTest, RequestTest, ResponseTest, RouterTest, ValidatorTest, YamlTest
│   │   ├── Http/               ← KernelTest, ExceptionRendererTest
│   │   ├── Log/                ← ContextSanitizerTest, ErrorHandlerTest (processus a part)
│   │   └── Orm/                ← CasterTest, ConnectionTest (transactions), Query/QueryBuilderTest
│   ├── Docs/                   ← OpenApiSpecTest (fusion), OpenApiContractTest (spec = routes + DTO)
│   └── Model/
│       ├── Dao/                ← ModelTest, CategoryTest (SQLite en memoire)
│       ├── Mappers/            ← EventMapperTest, CategoryMapperTest
│       └── Services/           ← EventServiceTest (SQLite en memoire)
├── Integration/                ← vraies requetes HTTP, rangees par classe responsable du comportement
│   ├── Controller/             ← EventControllerTest, HealthControllerTest, DocsControllerTest (docs coupees)
│   └── Core/                   ← RouterTest, Http/{Kernel,Cors,ExceptionRenderer}Test, Log/AccessLogTest
├── Support/                    ← doublures partagees (EchoController, AppRouter)
├── fixtures/                   ← scripts lances dans un processus a part
└── TestCase.php, HttpTestCase.php, run.php, run.sh
```

- `tests/Unit/` (tests unitaires) : classes testees directement, sans serveur ni MariaDB.
- `tests/Integration/` (tests d'integration) : vraies requetes HTTP sur l'application complete. `run.sh` remet a zero la base **`dayout_test`**
  (`infra/sql/reset_db.sh` : schema, migrations, seed), puis lance `php -S` sur un port libre
  branche sur cette base, avec des logs dans un dossier temporaire. La base de dev
  et `storage/logs/` ne sont jamais touches. Autre base : `TEST_DB_NAME=... back/tests/run.sh`.
- PHP : `php` du PATH, sinon `C:\xampp\php\php.exe` (ou `PHP=/chemin/php`).

Ajouter un test : une classe `<Classe>Test` au chemin de la classe testee, sous `tests/Unit/`
(herite de `Tests\TestCase`) ou `tests/Integration/` (herite de `Tests\HttpTestCase`), une
methode publique `test*` par cas ; `setUp()` avant chacune, `tearDown()` apres, meme en echec
(remettre une variable d'env...). Une doublure utilisee par plusieurs tests va dans `tests/Support/`.
Les tests d'integration s'appuient sur les donnees de `infra/sql/seed.sql`, datees par rapport au
2026-10-19 : une liste d'events passe `from=2026-10-19T00:00:00Z` pour ne pas dependre du jour du test.

```php
// tests/Integration/Controller/EventControllerTest.php
namespace Tests\Integration\Controller;

final class EventControllerTest extends HttpTestCase
{
    public function testShowUnknownEventReturns404(): void
    {
        $error = $this->assertError(404, 'EVENT_NOT_FOUND', $this->get('/events/99999'));
        $this->assertSame(['id' => 99999], $error['details']);
    }
}
```

Assertions : `assertSame`, `assertTrue` / `assertFalse`, `assertCount`, `assertArrayHasKey`,
`assertStringContains`, `assertThrows`, `self::payload($response)` (corps JSON d'une `Response`) ; en HTTP `assertStatus`, `assertError`, et
`get` / `post` / `request` qui renvoient un `TestResponse` (`status`, `header()`, `json()`).

### Analyse statique (PHPStan)

```bash
back/tools/phpstan.sh                # config : back/phpstan.neon
```

Sans Composer : le script telecharge `phpstan.phar` (version epinglee) dans `tools/.cache/`
(ignore par git) et verifie son empreinte SHA-256 avant chaque lancement. Niveau 8 (types des
parametres et retours, code mort, `match` non exhaustif, valeurs `null` et `false` possibles...), avec `phpVersion: 80200` pour
refuser toute syntaxe plus recente que le minimum supporte. Seule exception globale :
le type des valeurs des tableaux (`array<string, mixed>`), trop verbeux pour des lignes SQL et du JSON.
Ignorer une ligne precise : `// @phpstan-ignore <identifiant> (raison)`.

### A la main

```bash
curl http://localhost:8000/health
curl http://localhost:8000/events
curl 'http://localhost:8000/events?limit=5&offset=0'
curl 'http://localhost:8000/events?from=2026-10-19T00:00:00Z'   # events termines apres cette date
curl 'http://localhost:8000/events?limit=500'                   # 422 VALIDATION_ERROR
curl http://localhost:8000/events/1
curl http://localhost:8000/events/9999   # 404 EVENT_NOT_FOUND
curl http://localhost:8000/events/abc    # 404 NOT_FOUND (regex)
```

## Ajouter une route

1. Ecrire le `Model` dans `src/Model/Dao/` (herite de `App\Model\Dao\Model`, voir *ORM* ci-dessous)
   et le service dans `src/Model/Services/` : il appelle les DAO, applique les regles metier
   (`throw UserException::notFound($id)`, voir *Gestion des erreurs*) et renvoie des DTO.
2. Ecrire le DTO dans `src/Model/Dto/` (`final readonly class ... implements JsonSerializable` :
   proprietes en camelCase, `jsonSerialize()` donne les noms publics en snake_case, et seulement
   les champs a exposer) et son mapper dans `src/Model/Mappers/` (`toDto(array $row)`, `toDtos(array $rows)`).
   Le service renvoie des DTO, jamais une ligne brute du Model.
   Si la ressource est ecrite : un DTO d'entree **distinct** (ex. `EventInputDto`), qui porte ses
   regles de validation (`RULES`) et ne contient que ce qu'un client peut envoyer (ni id, ni compteurs,
   ni champs serveur). Le mapper le construit depuis le JSON (`fromInput($json)` : valide, regles
   croisees comprises comme `is_paid` / `price_cents`, `ValidationException` -> 422 sinon)
   et en tire les colonnes (`toRow($inputDto)`) :
   ```php
   $input = EventMapper::fromInput($request->json());
   $id    = $this->events->create(EventMapper::toRow($input), ['creator_id' => $userId, 'status' => 'draft']);
   ```
3. Ecrire le `Controller` dans `src/Controller/` (herite de `App\Core\Controller`) ;
   il instancie son service dans son constructeur (`$users ?? new UserService()`). Chaque action
   recoit la `Request` et **renvoie** une `Response` ; logique HTTP seulement (parametres, statut) :
   ```php
   public function show(Request $request): Response
   {
       return $this->ok($this->users->getPublic((int) $request->param('id')));  // 404 leve par le service
   }
   ```
   Helpers : `ok($data, $status)`, `noContent()`, `validate($request, $rules)` (body JSON),
   `validateQuery($request, $rules)` (query string : 422 si invalide, jamais de valeur par defaut
   silencieuse). Pour echouer : laisser remonter l'exception metier du service, ou `throw HttpException`.
   Requete : `param('id')`, `json()`, `query()`, `header()`, `attribute('user')`.
4. Documenter la route et ses schemas dans `docs/openapi/<ressource>.yaml` (sinon `OpenApiContractTest` echoue).
5. Declarer les routes dans `routes/<ressource>.php` (ex. `routes/users.php`),
   charge automatiquement par `routes/api.php` (sauf `docs.php`, charge selon `DOCS_ENABLED`).
   Parametre : `{id}` (tout sauf `/`) ou `{id:regex}`, accolades admises (`{code:[a-z]{2}}`) ;
   le reste du chemin est pris a la lettre (`.` n'est pas un joker) :
   ```php
   <?php
   declare(strict_types=1);

   use App\Controller\UserController;

   /** @var \App\Core\Router $router */
   $router->get('/users/{id:\d+}', [UserController::class, 'show']);
   ```
6. Routes protegees : des middlewares (`App\Core\Http\Middleware`), par route ou par groupe.
   Un groupe applique son prefixe et ses middlewares a toutes ses routes (imbricable) :
   ```php
   $router->group('/events', [Authenticate::class], function (Router $r) {
       $r->post('', [EventController::class, 'create'], [new RequireRole('creator')]);
       $r->delete('/{id:\d+}', [EventController::class, 'delete'], [new RequireRole('admin')]);
   });
   ```
   Un middleware peut refuser (`throw new HttpException(401, ...)`), enrichir la requete
   pour l'action (`$next($request->withAttribute('user', $user))`) ou agir apres
   (`$next($request)->withHeader(...)`) :
   ```php
   final class Authenticate implements Middleware
   {
       public function process(Request $request, callable $next): Response
       {
           $user = /* session -> utilisateur */ null;
           if ($user === null) {
               throw new HttpException(401, 'UNAUTHENTICATED', 'Login required');
           }
           Log::setUserId($user['id']);
           return $next($request->withAttribute('user', $user));
       }
   }
   ```

## ORM

ORM maison minimal : le moteur est dans `src/Core/Orm/`, les models dans
`src/Model/Dao/`.

| Classe                  | Role                                                                  |
| ----------------------- | --------------------------------------------------------------------- |
| `Dao\Model`             | base des models : definition de table (constantes), `find`, `create`... |
| `Query\QueryBuilder`    | requetes fluentes (`where`, `join`, `orderBy`, `get`, `count`...)     |
| `Query\Compiler`        | assemble le SQL ; `Query\Grammar` valide et quote les identifiants    |
| `Connection`            | execute le SQL via PDO (bindings, transactions)                       |
| `Caster`                | casts des lignes lues (`int`, `bool`, `datetime`...)                  |
| `Rows`                  | `keyBy` / `groupBy` pour assembler des chargements batch              |

Les lignes sont des tableaux associatifs deja castes, directement passables
au mapper du service. Un model construit sans argument utilise la `Connection`
partagee (`Database::orm()`) ; on peut lui en passer une autre pour les tests.

**Declarer un model :**
```php
// src/Model/Dao/User.php
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

// src/Model/Services/UserService.php
public function __construct(?User $users = null)
{
    $this->users = $users ?? new User();
}
```

| Constante     | Role                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `TABLE`       | nom de la table (obligatoire)                                        |
| `PRIMARY_KEY` | `id` par defaut                                                      |
| `COLUMNS`     | colonnes lues par defaut (le contrat API, lui, est dans `Model/Dto/`) — `*` par defaut |
| `FILLABLE`    | colonnes de la saisie client acceptees par `create()` / `updateById()`, le reste est ignore |
| `CASTS`       | `int`, `float`, `bool`, `datetime` (ISO 8601 UTC), `json`            |

**Lire** : uniquement dans le model, qui expose ses methodes nommees. `find()`,
`findMany()` et `all()` sont `protected` : ils ignorent les regles de visibilite
(un `find()` public renverrait un event `draft`).
```php
// dans le model User :
public function findPublicById(int $id): ?array { return $this->find($id); }
$this->findMany([1, 2, 2, 3]);                   // [1 => [...], 2 => [...], 3 => [...]] (1 requete)

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
$id = $users->create($data, ['password_hash' => Password::hash($password)]);  // id insere
$users->updateById($id, ['first_name' => 'B']);                               // nb lignes
$users->deleteById($id);
// Rien a ecrire (body vide ou sans champ FILLABLE) -> ValidationException, 422 ({"body": "No writable field..."}).

// Requete libre (pas de filtre FILLABLE ni de casts) : $this->table() dans un
// model, ou Database::orm()->table(...) ailleurs.
$this->table()->where('event_id', 4)->where('status', 'no_show')->update(['status' => 'cancelled']);

// Transaction : la Connection est partagee, donc tous les models construits
// sans argument participent a la meme transaction.
Database::orm()->transaction(function (Connection $db) use ($events, $registrations) {
    // tout ou rien ; la valeur retournee est renvoyee par transaction()
});
// Imbricable : un transaction() interne pose un SAVEPOINT. S'il echoue et que l'appelant
// rattrape l'exception, seul son travail est annule ; sinon tout est annule.
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

// Service : 2 requetes au total, quel que soit le nombre d'events (cf. EventService::withCategories)
$items   = $this->events->listPublishedEndingAfter($from, $limit, $offset);
$byEvent = $this->registrations->listForEvents(array_column($items, 'id'));
$items   = array_map(fn (array $e): array => $e + ['registrations' => $byEvent[$e['id']] ?? []], $items);
```

Garde-fous : valeurs toujours bindees (`?`), noms de colonnes valides par regex
au moment de l'appel et entoures de backticks, operateurs, sens de tri et types
de jointure en whitelist, `update()` / `delete()` refuses sans `WHERE`,
`where('col', '>', null)` refuse (toujours faux en SQL).
`selectRaw()` / `whereRaw()` acceptent du SQL brut : n'y concatener **jamais**
une entree utilisateur (passer par les bindings de `whereRaw`). `whereRaw()` est
toujours entoure de parentheses : un `OR` interne n'annule pas les autres conditions.
Les erreurs SQL (contraintes, triggers `SIGNAL 45000`) remontent en `PDOException`,
traduites en reponse HTTP par `DbErrorMapper` (voir *Gestion des erreurs*).

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
{"timestamp":"2026-10-24T09:12:03.481Z","level":"error","channel":"error","message":"Database error","requestId":"9f2c4e1a7b3d5f60","userId":null,"method":"GET","path":"/events","context":{"exception":{"class":"PDOException","message":"...","code":"42S22","file":"...:42","trace":["..."]}}}
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
  `Permissions-Policy`, `CSP` (API-only ; `/docs` a la sienne), envoyes par PHP sur toutes les reponses,
  erreurs comprises (`App\Core\Http\SecurityHeaders`, identiques sous Apache et `php -S`)
- `.htaccess` bloque les dotfiles et extensions sensibles (`.env`, `.sql`, …)
- Variables d'env validees au demarrage par `App\Core\Config` (`DB_HOST`, `DB_PORT`,
  `DB_NAME`, `DB_USER` obligatoires, entiers, booleens, `LOG_LEVEL` et `APP_TIMEZONE` controles)
- `/docs` et `/openapi.json` coupes par defaut (`DOCS_ENABLED`)
- Connexion BDD avec delai de 3 s : MariaDB injoignable -> echec rapide, pas de requete bloquee
- `Vary: Origin` sur toutes les reponses (cache partage sans fuite de CORS entre origines)
- Jamais de `$e->getMessage()` ou de trace dans la reponse HTTP (log serveur uniquement)
- `App\Core\Password` : bcrypt cost 12, pret pour l'auth future

## Flux d'une requete

```
HTTP -> public/.htaccess -> public/index.php
     -> autoload, ErrorHandler (filets), config/config.php (Config, Log, Database)
     -> routes/api.php -> routes/<ressource>.php (declarations) [+ routes/docs.php si DOCS_ENABLED]
     -> Kernel::handle(Request::fromGlobals())
          -> Router::dispatch : match {param}, middlewares, Controller::action -> Response
             -> Service (regles metier) -> Model (methode nommee) -> QueryBuilder -> Compiler (SQL)
                -> Connection (bindings) -> PDO -> MariaDB -> Caster (Model::CASTS) -> Mapper -> DTO
          -> exception levee n'importe ou -> ExceptionRenderer -> Response d'erreur
          -> SecurityHeaders + Cors + X-Request-Id sur toutes les reponses
     -> Response::send() (seul endroit qui ecrit statut, en-tetes et body)
```

## Gestion des erreurs

Aucun `try/catch` n'est necessaire dans les controllers : `Kernel` confie toute
exception a `App\Core\Http\ExceptionRenderer`.

- `App\Core\HttpException` -> renvoyee au client avec son statut, son code, son message
  et ses en-tetes. Reservee a la couche HTTP (routeur, `Request`, middlewares) ; un 404 de
  ressource vient du service (`EventException::notFound`, ci-dessous) :
  ```php
  throw HttpException::badRequest('Missing field', ['field' => 'title']);
  throw new HttpException(401, 'UNAUTHENTICATED', 'Login required');
  ```
- `App\Core\ValidationException` -> `422 VALIDATION_ERROR`, `details` = champ -> message. Levee
  par le `Validator`, les mappers (`fromInput`) et les DAO, sans dependre de HTTP :
  ```php
  throw ValidationException::field('end_at', 'Must be after start_at');
  ```
- `App\Model\Exceptions\BusinessException` -> regle metier levee par un service. Une classe
  par ressource, un cas par factory nommee ; le service ne connait pas HTTP, c'est
  `ErrorKind` qui fixe le statut (`NotFound` 404, `Forbidden` 403, `Conflict` 409,
  `Invalid` 422). `details` part au client, `context` reste dans `info-AAAA-MM-JJ.log` avec le
  `requestId` (renvoye au client dans l'en-tete `X-Request-Id`) :
  ```php
  // src/Model/Exceptions/EventException.php
  public static function notFound(int $id): self
  {
      return new self(ErrorKind::NotFound, 'EVENT_NOT_FOUND', 'Event not found', ['id' => $id]);
  }

  // dans EventService
  $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
  ```
- regle metier d'un trigger SQL (`SIGNAL ... MYSQL_ERRNO = 50xxx`) -> traduite par
  `App\Core\Http\DbErrorMapper`, avec le message du trigger. Table complete des
  codes : `infra/README.md`, section *Codes d'erreur des triggers*.

  | errno         | HTTP | code                         |
  | ------------- | ---- | ---------------------------- |
  | 50101         | 422  | `EMAIL_DOMAIN_NOT_ALLOWED`   |
  | 50201, 50202  | 422  | `INVALID_SCHOOL_RESTRICTION` |
  | 50301         | 403  | `USER_BANNED`                |
  | 50302         | 403  | `RELIABILITY_TOO_LOW`        |
  | 50303         | 409  | `EVENT_NOT_OPEN`             |
  | 50304         | 409  | `EVENT_FULL`                 |
  | 50401 - 50404 | 403  | `ADMIN_REQUIRED`             |
  | 50501         | 422  | `INVALID_PLANNING_ITEM`      |
  | 50502         | 422  | `INVALID_REPORT_TARGET`      |
  | autre 50xxx   | 409  | `BUSINESS_RULE_VIOLATION`    |

  Ajouter une regle : choisir un errno libre dans la plage du domaine, l'ajouter a
  `DbErrorMapper::TRIGGER_ERRORS` et aux deux README.
- contrainte native -> message generique (le message MariaDB cite tables et contraintes) :
  doublon `1062` et suppression d'une ligne referencee `1451` -> `409 CONFLICT`,
  cle etrangere inexistante `1452`, CHECK `4025`, chaine trop longue `1406` et nombre hors
  du type de la colonne `1264` -> `422 VALIDATION_ERROR`. Filet seulement : les `RULES` des DTO
  d'entree reprennent ces bornes pour que le client sache quel champ corriger.
  Chaque erreur SQL traduite est journalisee en `info` (`Database rule violated`, errno et code,
  sans le message natif, qui peut citer la valeur refusee).
- toute autre exception (PDO, logique, ...) -> journalisee dans `error.log`,
  le client recoit un `500 INTERNAL_ERROR` generique.

Hors du Kernel, `App\Core\Log\ErrorHandler` journalise les warnings PHP et repond
encore `500 INTERNAL_ERROR` en JSON pour une exception au demarrage (config invalide)
ou une erreur fatale (memoire, timeout).

Un `try/catch` ne se justifie que pour reagir a une erreur precise
(ex. `/health` qui repond `db: down` au lieu d'echouer).

## Validation des entrees

`App\Core\Validator` verifie les donnees recues et leve une `ValidationException` (422) si
elles sont invalides. Dans un controller, `$this->validate()` valide le body JSON,
`$this->validateQuery()` la query string :

```php
public function create(Request $request): Response
{
    $data = $this->validate($request, [
        'title'       => 'required|string|min:3|max:150',
        'start_at'    => 'required|datetime',
        'capacity'    => 'nullable|int|min:1',
        'is_paid'     => 'bool',
        'currency'    => 'in:EUR',
        'contact_url' => 'nullable|url',
    ]);
    // $data ne contient que les champs declares, `int` et `bool` deja castes.
    return $this->ok(['id' => $this->events->create($data, ['creator_id' => $userId])], 201);
}
```

Reponse en cas d'erreur (une erreur par champ, la premiere regle qui echoue) :
```json
{ "error": { "code": "VALIDATION_ERROR", "message": "Invalid input",
  "details": { "title": "This field is required", "capacity": "Must be at least 1" } } }
```

| Regle      | Accepte                                                              |
| ---------- | -------------------------------------------------------------------- |
| `required` | present, non `null`, ni chaine vide (apres trim), ni tableau vide    |
| `nullable` | `null` autorise (les autres regles sont alors ignorees)              |
| `string`   | chaine                                                               |
| `int`      | entier ou chaine d'entier (`"12"`, utile pour la query), caste en int |
| `number`   | entier, decimal ou chaine decimale (`"48.85"`), caste en float (coordonnees) |
| `bool`     | `true`/`false`, `1`/`0`, `"true"`/`"false"`, `"1"`/`"0"`, caste en bool |
| `array`    | tableau                                                              |
| `email`    | adresse email valide                                                 |
| `url`      | URL `http://` ou `https://` (refuse `javascript:`...)                |
| `date`     | `AAAA-MM-JJ`, date reelle (refuse `2026-02-31`)                      |
| `datetime` | ISO 8601 avec fuseau : `2026-11-07T20:00:00Z`, `...+01:00`           |
| `slug`     | minuscules, chiffres et tirets simples (`soiree-2026`, pas `Soiree--x`) |
| `min:n`    | longueur d'une chaine (en caracteres), valeur d'un int, taille d'un tableau |
| `max:n`    | idem                                                                 |
| `in:a,b`   | une des valeurs listees (sensible a la casse)                        |

Un champ absent et non `required` est simplement absent du resultat. Placer la
regle de type avant `min` / `max` (`int|min:1`). Les regles sont verifiees avant
les donnees : une regle inconnue ou un argument invalide (`max:abc`) leve une
`LogicException` (erreur de dev, donc 500), meme si le champ n'est pas envoye.
Pour valider la query string : `$this->validateQuery($request, [...])` (une valeur invalide
donne 422, jamais une valeur par defaut silencieuse).

## Conventions

- **Langue** : commentaires et documentation en francais (sans accents dans le code,
  comme le SQL) ; messages renvoyes au client, codes d'erreur et noms en anglais.
- **Statique ou instance** :
  - statique pour les fonctions pures sans etat : `Validator`, `Grammar`, `Compiler`,
    `Caster`, `Rows`, `ValueBinder`, `Password`, `SecurityHeaders`, `ExceptionRenderer`,
    constructeurs nommes de `HttpException` ;
  - instance pour ce qui porte un etat par requete ou des entrees/sorties : `Request`,
    `Response`, `Router`, `Kernel`, `Connection`, middlewares, controllers, models ;
  - facade statique configuree une fois au demarrage : `Log` et `Database` uniquement.
- **Configuration** : seul `App\Core\Config` lit l'environnement ; les autres classes
  recoivent leurs valeurs (`Log::configure()`, `Database::configure()`).
- **Models** : jamais de `find()` / `all()` publics ; une methode nommee par besoin
  metier, qui applique les regles de visibilite (`findPublishedById`).
- **Erreurs** : `throw HttpException` pour le client, n'importe quelle autre exception
  pour un bug ; pas de `Response::error()` dans les controllers.
