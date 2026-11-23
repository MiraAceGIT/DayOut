# CLAUDE.md — Constitution du projet DayOut

> Lu au début de chaque session. C'est le « second cerveau » du Tech Lead (toi + la session principale).
> Pour le détail, voir `README.md`, `back/README.md`, `front/README.md`, `infra/README.md`.

## Stack
- **Front** : HTML / CSS / JS vanilla (modules ES natifs, pas de build, pas de npm). Leaflet 1.9.4 via CDN (OpenStreetMap).
- **Back**  : PHP 8.2+ vanilla (pas de framework). Composer uniquement pour autoload PSR-4 + `firebase/php-jwt`.
- **BDD**   : MariaDB 10.11+ (XAMPP en local, `dayout` / `dayout_test`).
- **Tests** : runner maison PHP (unit + integration MariaDB) côté back ; `node:test` (Node 22) côté front ; contrat OpenAPI entre les deux.
- **CI**    : `.github/workflows/ci.yml` — back (PHP 8.2 & 8.5) + PHPStan niveau 6, front (Node 22) + typecheck JSDoc.

## Architecture

**Back (`back/src/`)** — MVC + ORM maison, dépendances par le constructeur (`Container`), **jamais** de `new` d'un service/DAO dans le code applicatif.

```
Core/           Infra : Config, Env, Router, Request/Response, Validator, ORM (Dao, QueryBuilder, Connection)
Model/
  Dao/          Accès données, une classe par ressource. `find()`/`findMany()`/`all()` sont protected ;
                exposer des méthodes nommées (`findPublishedById`, …).
  Services/     Cas d'usage ; orchestrent Policies + DomainServices + DAO ; renvoient des DTO ; lèvent des BusinessException.
  DomainServices/ Règles métier **pures**, sans BDD, testables sans SQLite.
  Policies/     Droits sur une ressource.
  Assemblers/   Charge les relations en batch (anti N+1).
  Dto/          Contrat JSON (`final readonly`, props camelCase, `jsonSerialize()` en snake_case).
  Mappers/      Ligne DAO ↔ DTO. **Seuls** à connaître les noms publics ; aucune règle métier.
  Query/        Lit les query strings, valide, construit un `XxxSearchParams`.
  Enums/        Valeurs des ENUM MariaDB (statuts, rôles).
  Exceptions/   Une classe par ressource héritant de Core\BusinessException.
Controller/     Logique **HTTP** seulement : params, statut, DTO ; aucune règle métier.
```

**Front (`front/src/`)** — MVC avec séparation stricte vue/contrôleur/modèle.

```
app/         Boot, router SPA, routes.
core/        api.js (fetch wrappé), apiLimits, errors, geolocation, routes, config.
controller/  Orchestration page → vue + modèle. Un contrôleur ne manipule pas le DOM directement.
model/
  domain/    Règles métier pures (filtre, tri, match, search).
  state/     État de page (MapSearch, …).
  dto/       Types DTO JSDoc du contrat API (snake_case côté serveur).
  mappers/   DTO ↔ domaine (snake_case → camelCase, dates ISO, …).
  services/  Seuls à appeler l'API (`api.get`, `api.post`, …).
view/        Vues DOM, composants réutilisables, messages.
pages/       Point d'entrée d'une page (mount, teardown via AbortSignal).
shared/      Utilitaires purs (format, geo, text, highlight, LatestTask, dom helpers).
```

**Règles d'import** :
- Un composant/vue n'appelle **jamais** `fetch` ou l'API directement → passer par `model/services/`.
- Le domaine n'importe jamais `core/` ni le DOM.
- Côté back, un Service n'appelle pas une autre ressource via HTTP — il injecte le Service voisin.

## Règles de code

- **PHP** : `declare(strict_types=1);` en tête de chaque fichier. `final readonly class` pour les DTO. Pas d'accents dans le code PHP et SQL. Nommage des classes `PascalCase`, méthodes/variables `camelCase`, constantes `UPPER_SNAKE_CASE`, noms publics API en `snake_case` (via DTO).
- **JS** : JSDoc + `tsc --checkJs` (strict). Pas de `any`. Modules ES.
- **Commentaires** : en **français**, uniquement le *pourquoi*. Jamais le *quoi* (c'est le code).
- **Validation à la frontière** : `Validator::check()` ou DTO d'écriture avec `RULES` côté back ; validation explicite côté front avant `fetch`.
- **SQL** : toujours des placeholders `?` bindés. `selectRaw` / `whereRaw` n'acceptent **jamais** d'entrée utilisateur concaténée.
- **Erreurs métier typées** : une `BusinessException` par ressource (ex. `EventException::notFound($id)`), `ErrorKind` fixe le statut HTTP. Jamais de `catch` vide.
- **Pas de magic numbers / strings** → constantes nommées (enum PHP ou const JS).
- **Pas de duplication** : à la 3e occurrence, on factorise (règle de trois).

## Workflow d'une feature

1. Si la feature touche l'archi (nouveau domaine, refacto transverse, choix de lib) → `/board` d'abord.
2. Test qui échoue (agent `testeur`).
3. Code minimal pour le faire passer (back + front si contrat API).
4. Refacto.
5. Agent `reviewer` sur le diff → corriger les BLOQUANT.
6. Commit conventionnel (hook `.githooks/commit-msg` l'exige) : `feat(scope):`, `fix(scope):`, `refacto(scope):`, `docs(scope):`, `style(scope):`, `test(scope):`, `chore(scope):`.

## Décisions

- Toute décision structurante → **ADR** dans `docs/adr/NNNN-titre.md` (template : `0000-template.md`).
- Avant de proposer un changement d'archi, lire les ADR existants.
- Toute nouvelle route API doit être documentée dans `back/docs/openapi/<ressource>.yaml` (le contrat est vérifié par `OpenApiContractTest`).

## Commandes

Depuis la racine du projet, en Git Bash :

```bash
# Backend
php -S localhost:8000 -t back/public          # API en dev
back/tests/run.sh                             # tests back (unit + integration)
back/tests/run.sh --unit                      # unit seuls (pas de MariaDB requise)
back/tests/run.sh --integration               # integration (reset `dayout_test`)
cd back && php tools/composer.phar stan       # PHPStan (niveau 8 en local, 6 en CI)

# Frontend
php -S localhost:5500 -t front                # front en dev
cd front && node --test "tests/**/*.test.js"  # tests front (Node 22)
bash front/tools/typecheck.sh                 # typecheck JSDoc (tsc --checkJs)

# Base de données
./infra/sql/reset_db.sh                       # schema + migrations + seed
./infra/sql/migrate.sh                        # migrations non jouées seulement
```

## Documentation de référence

- `README.md` (racine) — vue d'ensemble, installation.
- `back/README.md` — API, conventions back, ORM, erreurs, OpenAPI.
- `front/README.md` — pages, client API, Leaflet, charte, a11y.
- `infra/README.md` — BDD, migrations, codes d'erreur des triggers SQL.
- `http://localhost:8000/docs` — Swagger UI (dev, si `DOCS_ENABLED=true`).
