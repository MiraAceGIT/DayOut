# Backend DayOut — Documentation developpeur

## Documentations connexes

- Installation BDD, migrations, seed, codes triggers : [../infra/README.md](../infra/README.md)
- Front qui consomme cette API : [../front/README.md](../front/README.md)
- Spec OpenAPI interactive (Swagger UI) : `http://localhost:8000/docs` en dev (`DOCS_ENABLED=true`)

## 1) Contexte rapide

### Que fait ce backend ?

API REST pour **DayOut**, plateforme etudiante d'evenements inter-ecoles.
PHP vanilla (pas de framework) + MariaDB. Composer sert uniquement a l'autoload
PSR-4 et aux rares libs tierces (JWT), sans abstraction type Symfony/Laravel.

### Statut actuel

**V1 publique en lecture seule** — Les routes `GET /events`, `GET /events/{id}`,
`GET /health`, `GET /docs` et `GET /openapi.json` sont implementees et testees.
L'ORM maison, le logger, le routeur, la gestion d'erreurs et le middleware
d'auth (base) sont prets pour la suite.

### Perimetre exact

**Inclus** :

- Liste et detail d'evenements publies (triee, paginee, filtree par date)
- Categories (pastilles, filtres cote client)
- Documentation OpenAPI 3 servie en dev, verifiee par un test de contrat
- ORM maison (`App\Core\Orm`) : QueryBuilder fluide, casts, batch anti N+1
- Logger structure (JSON) avec `requestId`, audit separe, rotation quotidienne
- Validation d'entrees par regles (`Validator`) et DTO d'ecriture (`EventCreateDto`, `EventUpdateDto`)
- Gestion d'erreurs centralisee (exceptions HTTP, metier, triggers MariaDB)
- Runner de tests maison (unitaires + integration) sans framework

---

## 2) Prerequis & versions

| Outil        | Version  | Role                                              |
| ------------ | -------- | ------------------------------------------------- |
| **PHP**      | >= 8.2   | Teste sur 8.2 et 8.5 (CI)                         |
| **MariaDB**  | >= 10.11 | MySQL 8+ compatible                               |
| **Composer** | >= 2.5   | Autoload PSR-4 + deps ; phar fourni dans `tools/` |

### Extensions PHP requises

- `pdo_mysql` (PDO + MariaDB)
- `mbstring` (manipulation de chaines)
- `json` (fourni par defaut en PHP 8+)

### Dependances Composer

```json
"require": {
    "php": "^8.2",
    "ext-pdo": "*",
    "ext-json": "*",
    "ext-mbstring": "*",
    "firebase/php-jwt": "^7.2"
},
"require-dev": {
    "phpstan/phpstan": "2.1.17"
}
```

---

## 3) Installation

### Etapes

```bash
# 1. Depuis la racine du projet
cd back

# 2. Copier le fichier d'environnement
cp .env.example .env
# Editer .env si besoin (par defaut : XAMPP root sans mot de passe)

# 3. Installer les dependances PHP
composer install
# Si `composer` n'est pas dans le PATH :
curl -sSfL -o tools/composer.phar https://getcomposer.org/download/latest-stable/composer.phar
php tools/composer.phar install

# 4. S'assurer que la base `dayout` est importee (voir infra/README.md)
../infra/sql/reset_db.sh

# 5. Lancer l'API
cd ..
php -S localhost:8000 -t back/public
```

Si `vendor/autoload.php not found` apparait, c'est que `composer install` n'a
pas ete lance (le dossier `vendor/` est ignore par git).

### Fichiers ignores (.gitignore)

- `.env` (secrets)
- `vendor/` (deps Composer)
- `storage/logs/` (fichiers de logs)
- `tools/.cache/`, `tools/composer.phar`

---

## 4) Configuration (.env)

Le fichier `.env` est a la **racine de `back/`**. Il est charge et **valide** au
demarrage par `config/config.php` -> `App\Core\Config` : une variable obligatoire
manquante, un entier invalide, un booleen malforme (`true`/`false`, `1`/`0`,
`yes`/`no`, `on`/`off`) donne un **500 journalise** avec la cause dans
`error-AAAA-MM-JJ.log`.

### Variables

| Variable               | Defaut            | Description                                                         |
| ---------------------- | ----------------- | ------------------------------------------------------------------- |
| `APP_DEBUG`            | `false`           | Journalise aussi notices et deprecations (jamais affiche au client) |
| `APP_ENV`              | `dev`             | `dev` ou `prod`. `prod` : `MAIL_DEMO` refuse au demarrage, mails journalises sans leur contenu |
| `APP_TIMEZONE`         | `UTC`             | Fuseau PHP (whitelist)                                              |
| `DOCS_ENABLED`         | `true` (dev)      | Active `/docs` et `/openapi.json`. **Mettre `false` en prod.**      |
| `DB_HOST`              | **obligatoire**   | Hote MariaDB (XAMPP : `127.0.0.1`)                                  |
| `DB_PORT`              | **obligatoire**   | Port MariaDB (defaut `3306`)                                        |
| `DB_NAME`              | **obligatoire**   | Nom de la base (dev : `dayout`, tests : `dayout_test`)              |
| `DB_USER`              | **obligatoire**   | Utilisateur MariaDB (XAMPP : `root`)                                |
| `DB_PASSWORD`          | `""`              | Mot de passe (XAMPP : vide par defaut)                              |
| `CORS_ORIGIN`          | vide              | Origines autorisees (CSV). Vide = aucune origine cross-origin.      |
| `LOG_LEVEL`            | `info`            | `debug`/`info`/`notice`/`warning`/`error`/`critical`/`alert`/`emergency` |
| `LOG_PATH`             | `storage/logs`    | Relatif a `back/` ou absolu                                         |
| `LOG_RETENTION_DAYS`   | `30`              | Purge des `error-*.log` et `info-*.log`                             |
| `AUDIT_RETENTION_DAYS` | `365`             | Purge des `audit-*.log`                                             |
| `FRONT_URL`            | `http://localhost:5500` | Adresse du front (URL http(s)) : base des liens envoyes par mail |
| `MAIL_DEMO`            | `false`           | `true` : `POST /users` renvoie aussi le lien de confirmation (demo sans serveur de mail). **`false` en prod.** |
| `TRUSTED_PROXIES`      | _(vide)_          | Reverse proxies devant l'API (IP ou CIDR, separees par des virgules) : `X-Forwarded-For` n'est lu que derriere eux. **A renseigner en prod derriere un proxy.** |
| `RATE_LIMIT_REGISTRATIONS_PER_HOUR` | `5` | Inscriptions reussies par client et par heure |
| `RATE_LIMIT_REGISTRATION_ATTEMPTS_PER_HOUR` | `30` | Tentatives d'inscription (refusees ou non) par client et par heure |
| `RATE_LIMIT_EMAIL_VERIFICATIONS_PER_HOUR` | `30` | Essais de confirmation par client et par heure |
| `RATE_LIMIT_SECRET`    | _(secret de dev)_ | Cle HMAC des compteurs par IP (aucune adresse retrouvable depuis la base). **Obligatoire en prod**, 32 caracteres au moins (`openssl rand -hex 32`) |

### Commentaires dans le `.env`

`# ...` en fin de ligne est un commentaire, **sauf dans une valeur entre
guillemets** (`VAL="a#b"`). Lecture par `App\Core\Env` (teste par `EnvTest`).

---

## 5) Base de donnees (XAMPP + MariaDB)

Voir [`../infra/README.md`](../infra/README.md) pour :

- Installation XAMPP + phpMyAdmin
- Import du schema (`infra/sql/schema/*.sql`) et du seed (`infra/sql/seed.sql`)
- Migrations (`infra/sql/migrations/`) et journal `schema_migrations`
- Codes d'erreur des triggers metier (`SIGNAL SQLSTATE '45000'`, voir section 13)

### Scripts utiles

```bash
./infra/sql/reset_db.sh      # reset total : schema + migrations + seed
./infra/sql/migrate.sh       # applique uniquement les migrations non jouees
```

La base de **tests** (`dayout_test`) est remise a zero automatiquement par
`back/tests/run.sh` avant chaque campagne d'integration. La base de dev n'est
**jamais** touchee par les tests.

---

## 6) Lancement en local

### Option A — Serveur PHP integre (recommande en dev)

Depuis la racine du projet :

```bash
php -S localhost:8000 -t back/public
```

L'API ecoute sur `http://localhost:8000`.

### Option B — XAMPP Apache

Placer le projet dans `C:\xampp\htdocs\dayout` puis ouvrir
`http://localhost/dayout/back/public/health`.

Le prefixe `/dayout/back/public` est **retire automatiquement** du chemin
(deduit de `SCRIPT_NAME`).

### Verification

```bash
curl http://localhost:8000/health
# -> {"data":{"api":"up","db":"up","time":"2026-11-06T..."}}
```

---

## 7) Architecture du code (MVC + ORM maison)

### Structure

```
back/
├── public/
│   ├── index.php       ← front controller unique
│   └── .htaccess       ← rewrite + blocage des fichiers sensibles
├── src/
│   ├── Core/           ← infrastructure : Config, Env, Container, Clock (SystemClock), Router, RouteCompiler, Request, Response, Controller,
│   │                     Database, Validator, HttpException, BusinessException, ErrorKind
│   │   ├── Http/       ← Kernel, Middleware, ExceptionRenderer, DbErrorMapper,
│   │   │                 Cors, SecurityHeaders, RateLimit + RateLimiter (limitation de debit par IP)
│   │   ├── Log/        ← journalisation (error / info / audit) + ErrorHandler
│   │   ├── Mail/       ← Mailer (interface commune des mails) + LogMailer (mails ecrits dans mail-AAAA-MM-JJ.log)
│   │   └── Orm/        ← moteur ORM maison (Connection, Caster, QueryBuilder, Compiler, Grammar)
│   ├── Shared/         ← petits utilitaires purs, sans dependance a Core ni Model :
│   │                     Password, Yaml (lecteur YAML minimal)
│   ├── Model/          ← M
│   │   ├── Dao/        ← Dao.php (base), PageRequest + un dossier par ressource : Event/Event.php, Event/EventSearchParams.php,
│   │   │                 Event/EventRecord.php (ligne typee pour les regles) ;
│   │   │                 User/User.php (droits des comptes : isAdmin, canPublish)
│   │   ├── Enums/      ← valeurs des ENUM MariaDB : EventStatus (cycle de vie), Role, CreatorStatus
│   │   ├── Query/      ← lecture des query strings : EventFindQuery, CreatorFindQuery ; ParsesQueryString (trait commun)
│   │   ├── Services/   ← cas d'usage : orchestre policy, regles, DAO et assembler ; renvoie des DTO
│   │   ├── Policies/   ← droits sur une ressource (EventPolicy : createur approuve, proprietaire)
│   │   ├── DomainServices/ ← regles metier pures, sans base (EventDomainService : dates, prix, champs verrouilles, publication)
│   │   ├── Assemblers/ ← lignes -> DTO complets avec leurs relations en une requete chacune (EventAssembler)
│   │   ├── Exceptions/ ← une classe par ressource (EventException, FaqException), heritant de Core\BusinessException
│   │   ├── Dto/        ← contrat JSON, un dossier par ressource (Event/EventDto.php...) ; PageDto (commun)
│   │   ├── Mails/      ← texte des mails envoyes (ConfirmationMail)
│   │   └── Mappers/    ← ligne du DAO <-> DTO (toDto / toDtos / toRow / toUpdateRow)
│   ├── Docs/           ← OpenApiSpec : assemble docs/openapi/*.yaml
│   └── Controller/     ← C : logique HTTP, une classe par ressource ; Middleware/ : limites de debit par route
├── config/
│   ├── config.php      ← charge le .env (Core\Env), construit la Config
│   └── container.php   ← racine de composition (Container : Config, Connection, Mailer)
├── routes/             ← un fichier par ressource (charges par api.php)
├── docs/openapi/       ← spec OpenAPI 3 en YAML, un fichier par controller
├── storage/logs/       ← logs (crees a la volee, ignores par git)
├── tests/              ← runner maison : Unit/ + Integration/, meme arborescence que src/
├── composer.json       ← autoload PSR-4 : App\ -> src/, Tests\ -> tests/
└── README.md
```

### Patrons d'architecture

#### Controller (couche presentation HTTP)

- Logique HTTP uniquement (parametres, statut, helpers `ok()`, `noContent()`)
- Recoit son service par le constructeur (`public function __construct(private readonly UserService $users)`), construit par le `Container`
- **Aucune regle metier**
- Valide les inputs via `$this->validate()` (body) ou `$this->validateQuery()` (query string)
- Retourne une `Response` (DTO serialise sous `{ data: ... }`)

#### Service (regles metier)

- Orchestre : droits (`Policies/`), regles pures (`DomainServices/`), DAO, puis DTO (`Mappers/` ou `Assemblers/`) ; leve des **exceptions metier** (`EventException::notFound($id)`)
- Recoit ses dependances par le constructeur, jamais de `new` d'un DAO ou d'un service
- Renvoie des **DTO**, jamais une ligne brute du DAO
- Ne connait ni HTTP ni requete

#### DAO (acces donnees, dans `Model/Dao/`)

- Herite de `App\Model\Dao\Dao` et recoit la `Connection` par son constructeur (Container)
- Liste paginee : filtres propres, puis `return $this->paginate($query, $params->page, self::SORT_MAP);`
- Expose des **methodes nommees** (`findPublishedById`, `listAdmins`...) qui appliquent les regles de visibilite
- `find()`, `all()`, `findMany()` sont `protected` (jamais exposes : un `find()` public renverrait un event `draft`)
- Travaille via `$this->query()` (QueryBuilder avec `COLUMNS`, `CASTS`, `FILLABLE`) ou `$this->table()` (builder brut)

#### DTO (contrat JSON)

- `final readonly class ... implements JsonSerializable`
- Proprietes en **camelCase** cote PHP ; `jsonSerialize()` renvoie les noms **snake_case** cote API
- Un DTO par usage, comme `XxxCreateDTO` / `XxxUpdateDTO` / `XxxDTO` en Pydantic :
  - `EventDto` : lecture (ce que l'API renvoie)
  - `EventCreateDto` : corps de creation, champs obligatoires et valeurs par defaut
  - `EventUpdateDto` : corps de modification partielle, tout optionnel ; `provided` distingue « absent » de « null »
- Les DTO d'ecriture portent leurs regles (`RULES` pour la creation ; l'update les derive avec `Validator::forUpdate()`, `required` devenant `filled`) et se construisent par `fromArray($json)`, qui valide (422 sinon)
  et ignore ce qu'un client ne peut pas ecrire (`id`, `creator_id`, `status`...)

#### Mapper

- Conversion **ligne du DAO <-> DTO**
- `toDto(array $row)`, `toDtos(array $rows)`, `toRow(CreateDto)`, `toUpdateRow(UpdateDto)` : aucune validation (faite par les DTO)
- Seuls a connaitre les noms du JSON (renommer un champ cote API ne touche qu'ici)

#### Exceptions

- **`HttpException`** (Core) : reservee a la couche HTTP (routeur, `Request`, middlewares)
- **`ValidationException`** (Core) : levee par `Validator`, mappers et DAO -> 422 avec `details = champ -> message`
- **`BusinessException`** (Core, heritee par `Model/Exceptions`) : regle metier levee par un service ; une classe par ressource, un cas par factory nommee ; `ErrorKind` fixe le statut (`NotFound` 404, `Forbidden` 403, `Conflict` 409, `Gone` 410, `Invalid` 422)

### Flux d'une requete

```
HTTP -> public/.htaccess -> public/index.php
     -> autoload, ErrorHandler, config/config.php (Config, Log)
     -> config/container.php (Container : Config + Connection, le reste par les constructeurs)
     -> routes/api.php -> routes/<ressource>.php [+ routes/docs.php si DOCS_ENABLED]
     -> Kernel::handle(Request::fromGlobals())
          -> Router::dispatch : match {param}, middlewares, Controller::action -> Response
             (controllers et middlewares obtenus du Container, dependances injectees)
             -> Service (Policy, DomainServices) -> DAO (methode nommee) -> QueryBuilder -> Compiler (SQL)
                -> Connection (bindings) -> PDO -> MariaDB -> Caster -> Assembler / Mapper -> DTO
          -> toute exception -> ExceptionRenderer -> Response d'erreur
          -> SecurityHeaders + Cors + X-Request-Id sur toutes les reponses
     -> Response::send() (seul endroit qui ecrit statut, en-tetes et body)
```

---

## 8) Modeles & regles metier

### FAQ

La ressource `/faq` suit la meme structure que les evenements :
`FaqFindQuery` valide la query string, puis `FaqSearchParams` porte les filtres
jusqu'au DAO. `FaqService` renvoie les DTO convertis par `FaqMapper`.

| Methode | Route | Comportement |
| --- | --- | --- |
| GET | `/faq` | Recherche paginee des questions actives |
| GET | `/faq/{id}` | Question active, sinon 404 `FAQ_NOT_FOUND` |
| POST | `/faq` | Creation, 201 avec la question creee |
| PATCH | `/faq/{id}` | Modification des seuls champs fournis, 200 |
| DELETE | `/faq/{id}` | Suppression, 204 sans corps ; 404 si absente |

La liste accepte `q` (question ou reponse, 100 caracteres maximum), `ids`
(50 identifiants maximum), `sort_by` (`sort_order`, `question`, `created_at`),
`sort_dir` (`asc`, `desc`), `limit` (1 a 100, defaut 20) et `offset`
(0 a 10000, defaut 0). Exemple :
`GET /faq?q=inscription&limit=10&offset=0`.
La reponse utilise `data: { items, limit, offset, has_more, total }` comme `/events`.
Le front FAQ charge les pages successives pour afficher toutes les questions.

Les ecritures utilisent `RequireAuth`, comme les evenements, puis verifient en base
que l'utilisateur est un administrateur non banni. Le middleware de verification
des tokens reste a raccorder sur cette branche : en son absence, les routes
d'ecriture repondent 401, comme celles des evenements. Elles acceptent `question`
(255 caracteres), `answer` (16000 caracteres), `sort_order` (entier non negatif,
defaut 0) et `active` (booleen, defaut true). Les textes vides et `null` sont refuses.
`question` et `answer` sont obligatoires a la creation ; un PATCH doit contenir
au moins un champ modifiable. Les champs inconnus sont ignores.
Un administrateur peut modifier, reactiver ou supprimer une question inactive ;
elle reste exclue des deux routes GET publiques.

Perimetre : cette ressource fournit la page publique et le CRUD API demande.
Les ecrans d'administration FAQ restent dans SCRUM-79, la gestion des utilisateurs
dans SCRUM-44 et l'authentification commune dans SCRUM-42. `User::isAdmin()` se limite
a la verification du droit d'ecriture FAQ ; aucun compte utilisateur n'est modifie.

### Inscription et confirmation de l'adresse

Un etudiant cree son compte avec l'adresse e-mail de son ecole (SCRUM-41), puis le confirme par un lien a usage
unique. Chaque classe a un role :

| Classe | Role |
| --- | --- |
| `Dto/User/UserCreateDto` | corps de `POST /users` : normalisation (e-mail en minuscules, espaces), regles simples et regles multi-champs (meme modele que les autres DTO d'ecriture) |
| `Policies/RegistrationPolicy` | ecole et domaine de l'e-mail, adresse deja prise ou compte a remplacer, slugs des centres d'interet |
| `Services/RegistrationService` | ecrit le compte, ses interets et la demande createur dans **une transaction**, puis demande le lien |
| `Services/EmailConfirmationService` | envoie le lien (jeton + mail) et confirme l'adresse (`POST /email-verifications`) |
| `DomainServices/ConfirmationTokenIssuer` | jeton (32 octets, 64 hex), son SHA-256, expiration 24 h ; reutilisable (mot de passe oublie) |
| `DomainServices/CreatorDisplayName` | nom public de la demande : l'association, ou « Prenom N. » a titre individuel |
| `Mails/ConfirmationMail` | sujet et texte du mail de confirmation (hors du service qui l'envoie) |
| `Core\Clock` | heure courante injectee (`SystemClock`, horloge fixe en test) : expirations testees sans SQL |

| Methode | Route | Comportement |
| --- | --- | --- |
| POST | `/users` | Inscription publique : 201 avec le compte cree (`email_verified: false`) |
| POST | `/email-verifications` | Confirme l'adresse avec `{ "token": "..." }` du lien, 200 |
| GET | `/schools` | Ecoles actives, triees par nom (liste du formulaire, domaines autorises) ; cache 1 h |
| GET | `/categories` | Categories actives, triees par libelle (centres d'interet) ; cache 1 h |

Regles de `POST /users` (toutes reverifiees par le serveur, meme si le front les controle deja) :

- `profile` : `participant` ou `creator` ; `first_name`, `last_name` (80 caracteres), `email` (sans espaces, stocke
  en minuscules), `school_id`, `password` (8 caracteres au moins, une majuscule et un chiffre, 72 octets au plus pour bcrypt),
  `terms_accepted` (doit valoir `true`), `interests` (au moins un slug de categorie active), `search_radius_km` (1 a 20, defaut 3).
- Createur : `organization_name` (association, club ou BDE, 120 caracteres), sauf `individual: true`.
- `website` : piege a robots, champ cache du formulaire. Rempli : `201` comme une vraie inscription (`id` 0), sans
  compte ni mail, audit `user.register` `blocked` : le robot n'apprend ni quel champ l'a trahi ni si l'adresse a un compte.
- Le domaine de l'e-mail doit etre celui d'une ecole **active** (sinon `422`, erreur sur `email`) et celui de
  l'ecole choisie (`422` sur `email`) ; ecole inconnue ou inactive : `422` sur `school_id`. Le trigger
  `trg_users_bi_whitelist` (`50101`, `422 EMAIL_DOMAIN_NOT_ALLOWED`) reste le dernier filet.
- Adresse deja utilisee, par un compte confirme ou par un compte non confirme dont le lien est encore valable :
  `409 EMAIL_TAKEN` (`details.field = email`). Un seul code pour les deux : l'existence du compte est revelee
  (le ticket demande de refuser un e-mail deja utilise), mais pas son etat. Compte non confirme dont le lien a expire :
  il est **remplace** dans la meme transaction (`ON DELETE CASCADE` : interets, demande createur, jetons), sinon une
  adresse prise par erreur ou au nom d'un autre etudiant le resterait pour toujours (audit : `replaced_unverified_user_id`).
  Deux inscriptions simultanees avec la meme adresse : la cle unique refuse la seconde, qui recoit aussi `409 EMAIL_TAKEN`.
- **Limites** par client et par heure, `429 TOO_MANY_REQUESTS` + `Retry-After` au-dela : 5 inscriptions reussies
  (`RegistrationRateLimit`, un formulaire refuse ne compte pas) et 30 tentatives, refusees ou non
  (`RegistrationAttemptRateLimit` : pas de sondage sans fin des adresses qui ont un compte). Par adresse e-mail, la
  limite existe de fait : `409` tant que le compte existe, donc au plus un mail par adresse et par 24 h. `Retry-After`
  donne l'attente reelle avant qu'une place se libere (pas toute la fenetre).
- Mot de passe hache par `Shared\Password` (bcrypt, cost 12), jamais stocke ni journalise en clair.
- Compte cree `role = participant`, `email_verified = 0`. Un createur a `creator_status = pending` et une ligne
  `creator_profiles` : il ne peut pas publier tant qu'un admin n'a pas valide sa demande (`User::canPublish`).
  **La liste des demandes a valider (SCRUM-45) doit ne garder que les comptes `email_verified = 1`.**
- Lien `FRONT_URL/confirm-email?token=...` ; seul le SHA-256 du jeton est stocke (`email_verifications`), valable 24 h,
  usage unique. Les jetons expires depuis plus de 7 jours sont purges apres chaque inscription, hors de sa transaction
  (pas de cron). Texte du mail : `Model/Mails/ConfirmationMail`.
- 201 sans en-tete `Location` : aucune route `GET /users/{id}` n'existe (le profil est SCRUM-43).
- Audit : `user.register`, `user.email_verified` (sans e-mail, mot de passe ni jeton).

`POST /email-verifications` : jeton mal forme ou inconnu `422 VERIFICATION_TOKEN_INVALID`, expire
`410 VERIFICATION_TOKEN_EXPIRED`, deja utilise `410 VERIFICATION_TOKEN_USED` (le lien a existe mais ne servira plus ;
`UPDATE ... WHERE used_at IS NULL`, atomique : deux clics simultanes ne confirment qu'une fois). 30 essais par adresse
IP et par heure (`EmailVerificationRateLimit`). Reponse : `{ email_verified: true, creator_status }` (`none` ou
`pending`). **Choix REST assume** : c'est la consommation d'un jeton, pas la creation d'une ressource, d'ou `200` et
non `201`. `POST` et non `GET` : un antivirus ou un apercu de messagerie qui ouvre le lien ne consomme rien (la page
front `/confirm-email` fait l'appel) ; jeton dans le corps et non dans l'URL : absent des logs d'acces.

Securite : route publique sans session, donc sans jeton CSRF. Un formulaire HTML d'un autre site ne peut envoyer que
du texte ou du multipart (`415`, corps JSON obligatoire) ; un `fetch` JSON d'un autre site passe par une
verification CORS refusee hors de `CORS_ORIGIN`. La connexion (SCRUM-42) devra refuser un compte `email_verified = 0`.

**Mails** : `App\Core\Mail\Mailer` (`send($to, $subject, $body)`) est l'interface commune, a reutiliser pour le
mot de passe oublie (SCRUM-56). Sans serveur de mail dans le projet, `LogMailer` ecrit chaque mail (une ligne JSON :
destinataire, sujet, corps) dans `LOG_PATH/mail-AAAA-MM-JJ.log`, a part des logs `info` / `audit`. En production
(`APP_ENV=prod`), le corps (qui contient le lien) n'est **pas** ecrit. En demo, `MAIL_DEMO=true` renvoie aussi le
lien dans `demo_confirmation_url` pour l'afficher a l'ecran ; `APP_ENV=prod` avec `MAIL_DEMO=true` est refuse au
demarrage. Un vrai envoi (SMTP) = une autre classe qui implemente `Mailer`, declaree dans `config/container.php` ;
le mail part aujourd'hui dans la transaction (un echec annule l'inscription), ce qui est instantane avec `LogMailer` :
avec SMTP, passer par une table d'envoi (outbox) pour ne pas garder la transaction ouverte pendant l'envoi reseau.

**Limitation de debit** (`Core\Http\RateLimit` + `RateLimiter`) : middleware generique par client, compteur en base
(`rate_limit_hits`, cle en HMAC-SHA256 avec `RATE_LIMIT_SECRET`, purge a chaque ajout). Une sous-classe par usage dans `src/Controller/Middleware/`
(constantes `BUCKET`, `WINDOW_SECONDS`, `COUNT_ONLY_SUCCESS` ; seuil `maxHits()` lu dans la Config), declaree sur sa
route. La requete est comptee **avant** d'etre traitee, puis retiree du compte si elle depasse la limite (ou, avec
`COUNT_ONLY_SUCCESS`, si elle echoue) : une rafale de requetes paralleles ne passe pas. Seuils dans `.env`
(`RATE_LIMIT_*_PER_HOUR`) : un campus derriere une seule IP publique (NAT) partage les limites, a relever si besoin.

Client (`Core\Http\ClientIp`) : `REMOTE_ADDR` (`Request::REMOTE_ADDR`), sauf si elle appartient a `TRUSTED_PROXIES`
(adresses ou plages CIDR) : `X-Forwarded-For` est alors lu de droite a gauche jusqu'a la premiere adresse qui n'est
pas un proxy de confiance (ce qu'un client ecrit lui-meme dans l'en-tete n'est jamais lu). **En production derriere un
reverse proxy, declarer son adresse**, sinon tous les etudiants partagent la limite du proxy. Une IPv6 compte pour
son /64 (un abonne recoit tout un /64).

### Declarer un DAO

```php
// Exemple : un DAO users plus complet que src/Model/Dao/User/User.php
final class User extends Dao
{
    protected const TABLE    = 'users';
    protected const COLUMNS  = ['id', 'email', 'first_name', 'last_name', 'role', 'banned', 'created_at'];  // jamais password_hash
    protected const FILLABLE = ['email', 'first_name', 'last_name'];  // saisie client uniquement
    protected const CASTS    = ['banned' => 'bool', 'created_at' => 'datetime'];

    public function findPublicById(int $id): ?array { return $this->find($id); }
    public function listAdmins(): array
    {
        return $this->query()->where('role', 'admin')->orderBy('last_name')->get();
    }
}
```

| Constante     | Role                                                                 |
| ------------- | -------------------------------------------------------------------- |
| `TABLE`       | nom de la table (obligatoire)                                        |
| `PRIMARY_KEY` | `id` par defaut                                                      |
| `COLUMNS`     | colonnes lues par defaut (le contrat API est dans `Model/Dto/`)      |
| `FILLABLE`    | colonnes acceptees par `create()` / `updateById()` (filtre la saisie) |
| `CASTS`       | `int`, `float`, `bool`, `datetime` (ISO 8601 UTC), `json`            |

### Entites principales (voir `infra/sql/schema/`)

- **users** : id, email, first_name, last_name, school_id, search_radius_km, role, creator_status, email_verified, banned, reliability_score, created_at
- **email_verifications** : user_id, token_hash (SHA-256), expires_at, used_at ; **user_interests** : user_id, category_id ;
  **creator_profiles** : user_id, display_name (demande createur)
- **schools** : id, name, email_domain (whitelist des domaines d'inscription)
- **events** : id, creator_id, title, slug, description, cover_image_url, start_at, end_at,
  venue_name, address_line, city, postal_code, latitude, longitude, capacity, registered_count,
  price_cents, currency, is_paid, external_ticket_url, school_restricted, restricted_school_id,
  status (`draft` / `published`), published_at, created_at
- **categories** : id, slug, label, active
- **event_categories** : association N<->N (events <-> categories)
- **registrations** : id, user_id, event_id, status (`confirmed` / `cancelled` / `no_show`)

### Regles metier gerees par triggers SQL

Voir *Gestion des erreurs* (section 13) et [`infra/README.md`](../infra/README.md).

- Domaine email verifie contre `schools.email_domain` (`50101`)
- Compatibilite entre `school_restricted` et `restricted_school_id` (`50201`, `50202`)
- Utilisateur banni (`50301`), score de fiabilite trop bas (`50302`)
- Event non ouvert (`50303`), event complet (`50304`)
- Role admin requis (`50401`-`50404`)

---

## 9) DTO & validation

### DTO de sortie (immuable)

```php
// src/Model/Dto/Event/EventDto.php
final readonly class EventDto implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $title,
        public string $slug,
        public ?string $coverImageUrl,
        public \DateTimeImmutable $startAt,
        public \DateTimeImmutable $endAt,
        // ...
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'cover_image_url' => $this->coverImageUrl,
            'start_at' => $this->startAt->format(DateTimeInterface::ATOM),
            // ...
        ];
    }
}
```

### DTO d'ecriture (avec regles de validation)

```php
// src/Model/Dto/Event/EventCreateDto.php : tout ce qu'il faut pour creer
final readonly class EventCreateDto
{
    public const RULES = [
        'title'    => 'required|string|max:180',
        'start_at' => 'required|datetime',
        'capacity' => 'nullable|int|min:1|max:4294967295',
        // ...
    ];

    public static function fromArray(array $json): self; // valide + regles croisees (dates, prix)
}

// src/Model/Dto/Event/EventUpdateDto.php : memes champs, tous optionnels
final readonly class EventUpdateDto
{
    public const RULES = [
        'title'    => 'string|max:180',   // pas de required ; null refuse (colonne NOT NULL)
        'capacity' => 'nullable|int|min:1|max:4294967295',
        // ...
    ];

    public static function fromArray(array $json): self; // 422 si corps vide
    public function has(string $field): bool;           // champ envoye ?
}
```

### Validator — regles disponibles

| Regle      | Accepte                                                              |
| ---------- | -------------------------------------------------------------------- |
| `required` | present, non `null`, ni chaine vide (apres trim), ni tableau vide    |
| `filled`   | si present : ni chaine vide (apres trim), ni tableau vide (PATCH)    |
| `nullable` | `null` autorise (les autres regles sont ignorees)                    |
| `string`   | chaine                                                               |
| `int`      | entier ou chaine d'entier (`"12"`), caste en int                     |
| `number`   | entier, decimal ou chaine decimale, caste en float                   |
| `bool`     | `true`/`false`, `1`/`0`, `"true"`/`"false"`, caste en bool           |
| `array`    | tableau                                                              |
| `email`    | adresse email valide                                                 |
| `url`      | URL `https://` publique : ni identifiants, ni `localhost`/IP privee, caracteres RFC 3986 |
| `date`     | `AAAA-MM-JJ` (refuse `2026-02-31`)                                   |
| `datetime` | ISO 8601 avec fuseau (`2026-11-07T20:00:00Z`, `...+01:00`)           |
| `slug`     | minuscules, chiffres et tirets simples                               |
| `min:n`    | longueur d'une chaine, valeur d'un int, taille d'un tableau          |
| `max:n`    | idem                                                                 |
| `in:a,b`   | une des valeurs listees (sensible a la casse)                        |

### Usage dans un controller

```php
public function create(Request $request): Response
{
    $dto = EventCreateDto::fromArray($request->json());
    $id = $this->events->create(EventMapper::toRow($dto), ['creator_id' => $userId, 'status' => 'draft']);
    return $this->ok(['id' => $id], 201);
}
```

Reponse en cas d'erreur (une par champ, premiere regle qui echoue) :

```json
{ "error": { "code": "VALIDATION_ERROR", "message": "Invalid input",
  "details": { "title": "This field is required", "capacity": "Must be at least 1" } } }
```

### Dates

- Format **ISO 8601** avec fuseau (`2026-11-07T20:00:00+00:00`, `DateTime::ATOM`)
- Toujours en **UTC** cote sortie

---

## 10) Mappers

### Localisation

Fichiers dans [`src/Model/Mappers/`](src/Model/Mappers/) :

- [`EventMapper.php`](src/Model/Mappers/EventMapper.php)
- [`CategoryMapper.php`](src/Model/Mappers/CategoryMapper.php)

### Operations

```php
EventMapper::toDto(array $row): EventDto                  // 1 ligne -> DTO
EventMapper::toDtos(array $rows): array                   // liste -> liste de DTO
EventMapper::toRow(EventCreateDto $dto): array            // creation -> colonnes SQL
EventMapper::toUpdateRow(EventUpdateDto $dto): array      // modification -> colonnes envoyees uniquement
```

### Regle

- Les mappers sont **les seuls** a traduire entre convention BDD (snake_case) et convention
  API (snake_case aussi dans la reponse, mais calculs camelCase cote PHP)
- Renommer un champ touche **uniquement** le mapper et le DTO
- **Aucune** logique metier (reservee au service)

---

## 11) Repositories (acces donnees) — ORM maison

### ORM

Le moteur est dans [`src/Core/Orm/`](src/Core/Orm/), les models dans [`src/Model/Dao/`](src/Model/Dao/).

| Classe                  | Role                                                                    |
| ----------------------- | ----------------------------------------------------------------------- |
| `Dao\Dao` (Model/)      | base des DAO : `find`, `findMany`, `create`, `updateById`, `deleteById`, `paginate` |
| `Query\QueryBuilder`    | requetes fluentes (`where`, `join`, `orderBy`, `get`, `count`...)       |
| `Query\Compiler`        | assemble le SQL ; `Query\Grammar` valide et quote les identifiants       |
| `Connection`            | execute le SQL via PDO (bindings, transactions, savepoints)             |
| `Caster`                | casts des lignes lues (`int`, `bool`, `datetime`, `json`...)            |
| `Rows`                  | `keyBy` / `groupBy` pour assembler des chargements batch                |

### Lecture

```php
$this->find($id);                                            // ignore la visibilite (protected)
$this->findMany([1, 2, 2, 3]);                               // [1 => [...], 2 => [...], 3 => [...]] (1 requete)

$this->query()->where('school_id', 1)->orderBy('last_name')->limit(20)->get();
$this->query()->where('reliability_score', '<', 40)->count();
$this->query()->whereIn('id', [1, 2, 3])->pluck('email');
$this->query()
    ->where(fn ($q) => $q->where('role', 'admin')->orWhere('banned', true))  // ( ... OR ... )
    ->whereNotNull('last_login_at')
    ->forPage(2, 20)
    ->get();
$this->query()->where('role', 'admin')->exists();
```

### Ecriture

```php
// 1er arg : saisie client (filtree par FILLABLE)
// 2e arg : champs fixes par le serveur (non filtres)
$id = $users->create($data, ['password_hash' => Password::hash($password)]);
$users->updateById($id, ['first_name' => 'B']);  // nb de lignes modifiees
$users->deleteById($id);

// Body vide ou sans champ FILLABLE -> ValidationException 422

// Requete libre (sans FILLABLE ni casts)
$this->table()->where('event_id', 4)->update(['status' => 'cancelled']);

// Transaction : le service recoit la Connection par son constructeur ; une seule par Container,
// donc tous les models de la requete participent
$this->connection->transaction(function (Connection $db) use ($events) {
    // tout ou rien
});
// Imbricable : la transaction interne pose un SAVEPOINT
```

### Jointures / agregats (`$this->table()` : builder brut)

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

### Regle anti N+1

Ne jamais appeler `find()` dans une boucle. Charger les relations en **une** requete,
puis les rattacher en PHP avec `Rows::keyBy` / `Rows::groupBy` :

```php
// Nombre de requetes constant, quel que soit le nombre d'events
[$items] = $this->events->findManyPublished($params);
$byEvent = $this->categories->listActiveByEvent(array_column($items, 'id'));
$items   = array_map(fn ($e) => $e + ['categories' => $byEvent[$e['id']] ?? []], $items);
```

### Garde-fous

- Valeurs toujours bindees (`?`), noms de colonnes valides par regex et quotes en backticks
- Operateurs, sens de tri, types de jointure en whitelist
- `update()` / `delete()` refuses sans `WHERE`
- `where('col', '>', null)` refuse (toujours faux en SQL)
- `selectRaw()` / `whereRaw()` : passer par les bindings, **jamais** concatener d'entree utilisateur
- Erreurs SQL (contraintes, triggers) remontees en `PDOException`, traduites par `DbErrorMapper` (section 13)

---

## 12) Controleurs (Router + Request/Response)

### Endpoints exposes

| Methode | Chemin               | Query params                                   | 200 — shape (sous `data`)             | Erreurs                                               |
| ------- | -------------------- | ---------------------------------------------- | ------------------------------------- | ----------------------------------------------------- |
| GET     | `/health`            | —                                              | `{ api, db, time }`                   | —                                                     |
| GET     | `/events`            | `limit` (1-100, def. 20), `offset` (0-10000, def. 0), `from` (ISO 8601, def. maintenant) + filtres (`EventFindQuery`, voir ci-dessous) | `{ items: Event[], limit, offset, has_more, total }` | `422 VALIDATION_ERROR` (param invalide ou hors bornes) |
| GET     | `/events/{id}`       | — (`id` entier via regex `\d+`)                | `Event` (avec `creator`)                | `404 EVENT_NOT_FOUND`, `404 NOT_FOUND` si `{id}` non num. |
| POST    | `/events`            | — ; body `EventCreate` ; **auth**              | `201` `Event` (cree en `draft`)       | `401`, `403 CREATOR_REQUIRED`, `409 CONFLICT` (slug), `415`, `422` |
| PATCH   | `/events/{id}`       | — ; body `EventUpdate` (partiel, ou `{"status": ...}` seul) ; **auth** | `Event`                               | `401`, `403`, `404`, `409 EVENT_INVALID_STATUS` / `EVENT_FIELDS_LOCKED`, `415`, `422` |
| DELETE  | `/events/{id}`       | — ; **auth** ; brouillons uniquement           | `204`                                 | `401`, `403`, `404`, `409 EVENT_INVALID_STATUS` |
| GET     | `/creators`          | `q`, `school_id`, `ids` (CSV), `has_upcoming_events`, `from`, `sort_by` (`display_name`, `created_at`), `sort_dir`, `limit`, `offset` (`CreatorFindQuery`) | `{ items: Creator[], limit, offset, has_more, total }` (approuves, non bannis) | `422 VALIDATION_ERROR` |
| POST    | `/users`             | — ; body `UserCreate` ; public                 | `201` `RegisteredUser` (non confirme) | `409 EMAIL_TAKEN` / `EMAIL_CONFIRMATION_PENDING` / `CONFLICT`, `415`, `422` (dont domaine refuse sur `email`, `EMAIL_DOMAIN_NOT_ALLOWED`) |
| POST    | `/email-verifications` | — ; body `{ token }` ; public                | `{ email_verified, creator_status }`  | `410 VERIFICATION_TOKEN_USED` / `_EXPIRED`, `415`, `422 VERIFICATION_TOKEN_INVALID`, `429` |
| GET     | `/schools`           | —                                              | `School[]` (actives, par nom), cache 1 h | —                                                  |
| GET     | `/categories`        | —                                              | `Category[]` (actives, par libelle), cache 1 h | —                                            |
| GET     | `/docs`              | —                                              | Swagger UI (hors enveloppe)           | `404` si `DOCS_ENABLED=false`                         |
| GET     | `/openapi.json`      | —                                              | spec OpenAPI 3 brute (hors enveloppe) | `404` si `DOCS_ENABLED=false`                         |

### Pagination des evenements

`EventController::findMany()` est l'equivalent de la methode `EvenementController::liste()`
du ticket. La query string est lue par `EventFindQuery` (`src/Model/Query`), qui valide
et convertit les filtres en `EventSearchParams`. Le DAO `Event::findManyPublished()` applique
le filtre `published`, `end_at > from` et les filtres optionnels, puis `paginate()` (herite du DAO de base)
applique le tri (`sort_by` puis `id`) et `LIMIT / OFFSET`, et renvoie `[items, total]`. Tri et fenetre voyagent
dans un `PageRequest` commun a toutes les listes : les regles `sort_by` / `sort_dir` / `limit` / `offset` viennent
de `ParsesQueryString::pageRules()`, les tris acceptes sont les cles de `SORT_MAP` du DAO (la premiere est le tri
par defaut). Le service renvoie un `PageDto` (generique, commun a toutes les listes).
Les evenements en cours sont inclus jusqu'a leur fin.

Filtres : `start_from` / `start_to` (debut), `q` (titre, lieu, ville), `city`,
`categories` et `ids` (CSV), `creator_id`, `is_free`, `available`, `sort_by`
(`start_at`, `created_at`, `title`, `price_cents`), `sort_dir` (`asc`, `desc`).

Chaque element porte son `creator`, comme le detail : categories et createurs sont charges
en une requete chacun pour toute la page (pas de N+1).

Exemples : `GET /events?limit=20&offset=0`, puis `GET /events?limit=20&offset=20`.
Conserver le meme `from` ISO 8601 entre les pages fixe le filtre temporel.
`total` vient d'un `COUNT(*)` sur la meme requete filtree, et `has_more` vaut
`offset + nombre d'elements < total`. Une derniere page pleine a aussi `has_more: false` ; un offset
au-dela des resultats renvoie `items: []`. Ce filtre temporel ne constitue pas un
instantane des donnees : une creation ou une modification concurrente peut encore deplacer les pages.

### Ecritures (create / update / delete)

`EventController::create()`, `update()` et `delete()` sont declares avec le middleware `RequireAuth`
(`src/Core/Http/RequireAuth.php`) : sans attribut `user_id` sur la requete, reponse `401 UNAUTHORIZED`
(en-tete `WWW-Authenticate: Bearer`). Cet attribut ne peut pas venir du client (ni query, ni header) :
seul le futur middleware d'authentification le posera apres verification du token. **Tant qu'il
n'existe pas, toutes les ecritures repondent 401.**

Toute ecriture exige un createur approuve (ou admin) non banni (`User::canPublish`), sinon
`403 CREATOR_REQUIRED` : un createur banni perd aussi la main sur ses events existants. Sur l'event
d'un autre : `403 EVENT_FORBIDDEN` s'il est publie, `404` sinon (un brouillon ne doit pas se reveler).
Le corps doit etre en `Content-Type: application/json` (`415` sinon : pas de requete CSRF « simple »).
Chaque ecriture est tracee par `Log::audit` (`event.create`, `event.update`...).

- `create` : l'event est cree en `draft` ; `creator_id` et `status` sont fixes par le serveur.
- `update` : en `draft` ou `published` seulement (`409 EVENT_INVALID_STATUS`). Les regles croisees
  (fin apres debut, prix / `is_paid`) se verifient avec les valeurs actuelles des champs non envoyes.
  Publie, `restricted_school_id` est fige ; avec des inscriptions, `start_at`, `end_at`, `is_paid`,
  `price_cents` et `currency` aussi (`409 EVENT_FIELDS_LOCKED`, `details.fields`). `capacity` ne peut
  pas descendre sous `registered_count` (`422` sur `capacity`).
- Changement de statut par `PATCH`, avec `status` seul dans le corps :
  - `{"status": "published"}` : `draft` -> `published` et `published_at`, refuse si l'event est deja termine ;
  - `{"status": "cancelled"}` : `published` -> `cancelled` ; inscriptions et historique conserves.
- `delete` : brouillons uniquement (`DELETE ... WHERE status = 'draft'`, atomique). Un event publie a un
  historique (inscriptions, clics, presences, signalements) que les `ON DELETE CASCADE` effaceraient :
  il s'annule.

### Detail d'un evenement

`EventController::findOne()` correspond a `EvenementController::detail($id)` dans le ticket.
`EventService::findOne()` charge l'evenement comme un element de liste :
`Event::findPublishedById()`, puis `Category::listActiveByEvent()` (categories actives,
ordre `label, id`) et `Creator::listPublicByIds()` (jointures `creator_profiles`, `schools`).

`registered_count` est la colonne denormalisee, tenue a jour par les triggers de
`registrations` (`sp_registered_count_add`, inscriptions actives au sens de
`fn_registration_is_active`) : la liste et le detail affichent le meme compteur.

Un seul DTO, `EventDto`, sert en liste et en detail ; il contient `creator`
(`id`, `display_name`, `description`, `avatar_url`, `school_name`). Le nom et la description
proviennent de `creator_profiles`, avec en secours le prenom et l'initiale du nom (« Alice A. »,
jamais le nom complet) et la bio utilisateur ;
l'ecole et l'avatar peuvent etre `null`. Aucun email, telephone, score de fiabilite ou
champ de moderation n'est expose. Les statuts non `published` et les events de createurs bannis
restent invisibles en lecture publique.

Avant l'acces a la base, `findOne()` rejette avec une 404 les identifiants non decimaux,
nuls, negatifs, avec zero initial ou hors de la plage des entiers PHP. Le service est
instancie a la premiere utilisation, apres validation : un identifiant invalide ne
depend pas de la disponibilite de la base. Les valeurs non numeriques sont aussi
rejetees par le routeur. Un evenement inexistant ou non publie produit la meme
erreur `EVENT_NOT_FOUND` (HTTP 404), sans indiquer s'il s'agit d'un brouillon ou d'une archive.

### Format de reponse

```json
// Succes
{ "data": { ... } }

// Erreur
{ "error": { "code": "NOT_FOUND", "message": "Event not found", "details": { "id": 42 } } }
```

### Declarer une route

```php
// routes/users.php
<?php declare(strict_types=1);
use App\Controller\UserController;

/** @var \App\Core\Router $router */
$router->get('/users/{id:\d+}', [UserController::class, 'show']);
```

Parametres : `{id}` (tout sauf `/`) ou `{id:regex}` ; `.` n'est pas un joker (le reste du chemin est pris a la lettre).

### Groupes et middlewares

```php
$router->group('/events', [Authenticate::class], function (Router $r) {
    $r->post('', [EventController::class, 'create'], [new RequireRole('creator')]);
    $r->delete('/{id:\d+}', [EventController::class, 'delete'], [new RequireRole('admin')]);
});
```

Un middleware peut refuser (`throw new HttpException(401, ...)`), enrichir la requete
(`$next($request->withAttribute('user', $user))`) ou agir apres (`$next($request)->withHeader(...)`).

### Action typique

```php
public function show(Request $request): Response
{
    return $this->ok($this->events->getPublic((int) $request->param('id')));  // 404 leve par le service
}
```

Helpers controller : `ok($data, $status)`, `noContent()`, `validate($request, $rules)`,
`validateQuery($request, $rules)`.

Acces a la requete : `param('id')`, `json()`, `query()`, `header()`, `attribute('user')`.

### Documentation OpenAPI

La source est en YAML dans [`docs/openapi/`](docs/openapi/) :

```
docs/openapi/
├── openapi.yaml   ← en-tete + composants communs (Error, ValidationError, NotFound...)
├── events.yaml    ← EventController : /events*, schemas Event, Category, EventPage
└── health.yaml    ← HealthController : /health, schema Health
```

Un fichier de controller ne contient que `tags`, `paths` et `components` ;
`App\Docs\OpenApiSpec` les fusionne dans `openapi.yaml`. `/openapi.json` sert
le resultat en JSON, relu a chaque appel.

`tests/Unit/Docs/OpenApiContractTest.php` echoue si la spec derive du code :
route declaree mais non documentee (ou l'inverse), fichier de routes sans YAML,
champs du schema `Event` / `Category` differents de `EventDto` / `CategoryDto`.

---

## 13) Gestion des erreurs

Aucun `try/catch` necessaire dans les controllers : `Kernel` confie toute exception
a `App\Core\Http\ExceptionRenderer`.

### Types d'exception

- **`HttpException`** -> renvoyee avec son statut, code, message, en-tetes. Reservee a la couche HTTP :
  ```php
  throw HttpException::badRequest('Missing field', ['field' => 'title']);
  throw new HttpException(401, 'UNAUTHENTICATED', 'Login required');
  ```
- **`ValidationException`** -> `422 VALIDATION_ERROR`, `details` = champ -> message :
  ```php
  throw ValidationException::field('end_at', 'Must be after start_at');
  ```
- **`BusinessException`** -> regle metier levee par un service. `ErrorKind` fixe le statut
  (`NotFound` 404, `Forbidden` 403, `Conflict` 409, `Gone` 410, `Invalid` 422) ; `details` part au client,
  `context` reste dans `info-*.log` avec le `requestId` :
  ```php
  // src/Model/Exceptions/EventException.php
  public static function notFound(int $id): self
  {
      return new self(ErrorKind::NotFound, 'EVENT_NOT_FOUND', 'Event not found', ['id' => $id]);
  }
  // dans EventService
  $event = $this->events->findPublishedById($id) ?? throw EventException::notFound($id);
  ```

### Codes d'erreur des triggers SQL

Les triggers SQL utilisent `SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50xxx` ; `DbErrorMapper` traduit :

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

Ajouter une regle : choisir un errno libre, l'ajouter a `DbErrorMapper::TRIGGER_ERRORS` et aux deux README.

### Contraintes natives

- doublon `1062` et suppression d'une ligne referencee `1451` -> `409 CONFLICT`
- FK inexistante `1452`, CHECK `4025`, chaine trop longue `1406`, nombre hors bornes `1264` -> `422 VALIDATION_ERROR`

Chaque erreur SQL traduite est journalisee en `info` (`Database rule violated`, errno et code, sans le message natif).

### Autres exceptions

- Toute autre exception (PDO, logique) -> journalisee dans `error.log`, client recoit un `500 INTERNAL_ERROR` generique
- Hors du Kernel, `App\Core\Log\ErrorHandler` journalise les warnings PHP et repond `500 INTERNAL_ERROR` en JSON meme pour une exception au demarrage

### Codes emis globalement

`BAD_REQUEST`, `NOT_FOUND`, `METHOD_NOT_ALLOWED`, `VALIDATION_ERROR`, `CONFLICT`,
codes metier (`EVENT_NOT_FOUND`, `EMAIL_TAKEN`, `VERIFICATION_TOKEN_*`...), `TOO_MANY_REQUESTS`, codes triggers (`EVENT_FULL`...),
`INTERNAL_ERROR`, `ENCODING_ERROR`.

---

## 14) Base de donnees (detail backend)

### Moteur utilise

- **Developpement / prod** : MariaDB 10.11+ (XAMPP)
- **Tests unitaires** : SQLite en memoire (`:memory:`) pour tester les DAO, `QueryBuilder`, services sans serveur
- **Tests d'integration** : MariaDB sur la base `dayout_test` (remise a zero par `run.sh`)

### Connexion

- `config/container.php` declare la `Connection` (`Database::connection($config)`) ; les models la recoivent par leur constructeur
- Ouverture paresseuse : le PDO n'est cree qu'au premier SQL (une requete rejetee en 404 / 422 ne touche pas la base)
- PDO `ERRMODE_EXCEPTION` + `EMULATE_PREPARES=false` + placeholders `?` bindes (anti-SQLi)
- Timeout de connexion : **3 secondes** (MariaDB injoignable -> echec rapide, pas de requete bloquee)

### Schema et migrations

- Schema de depart : [`infra/sql/schema/`](../infra/sql/schema/) (un fichier par domaine, triggers a part)
- Migrations : [`infra/sql/migrations/`](../infra/sql/migrations/), un fichier par evolution, jouees dans l'ordre par `migrate.sh`
- Journal : table `schema_migrations` (nom du fichier + horodatage)

### Seed

Fichier : [`infra/sql/seed.sql`](../infra/sql/seed.sql).

Contient :
- 15 ecoles (domaines d'inscription whitelist)
- 14 utilisateurs
- 10 evenements
- 16 inscriptions

Les donnees sont **datees par rapport au 2026-10-19** : les tests d'integration qui listent
des events passent `from=2026-10-19T00:00:00Z` pour ne pas dependre du jour du test.

---

## 15) Tests

### Runner maison (pas de PHPUnit)

Depuis Git Bash, a la racine du projet :

```bash
back/tests/run.sh                 # tout : unitaires + integration
back/tests/run.sh EventMapperTest # filtre sur "Dossier/Classe::methode"
back/tests/run.sh Model/          # tous les tests d'un dossier
back/tests/run.sh --unit          # unitaires seuls, sans BDD
back/tests/run.sh --integration   # integration seule (remet dayout_test a zero)
```

Via Composer :

```bash
composer test                 # unitaires + integration
composer test:unit            # unitaires seulement
composer test:integration     # integration seulement
```

### Arborescence

Les tests suivent l'arborescence de `src/` : le test de `src/Core/Http/Kernel.php` est
`tests/Unit/Core/Http/KernelTest.php` (namespace `Tests\Unit\Core\Http`), un fichier par classe testee.

```
tests/
├── Unit/                       ← classe testee directement, sans serveur ni MariaDB
│   ├── Controller/             ← DocsControllerTest
│   ├── Core/                   ← ConfigTest, ContainerTest, EnvTest, RequestTest, ResponseTest, RouterTest, ValidatorTest
│   │   ├── Http/               ← KernelTest, ExceptionRendererTest, RateLimitTest
│   │   ├── Mail/               ← LogMailerTest (contenu jamais journalise en production)
│   │   ├── Log/                ← ContextSanitizerTest, ErrorHandlerTest (processus a part)
│   │   └── Orm/                ← CasterTest, ConnectionTest (transactions), Query/QueryBuilderTest
│   ├── Docs/                   ← OpenApiSpecTest, OpenApiContractTest
│   └── Model/
│       ├── Dao/                ← DaoTest, CategoryTest (SQLite en memoire)
│       ├── Enums/              ← EventStatusTest
│       ├── Mappers/            ← EventMapperTest, CategoryMapperTest
│       ├── DomainServices/     ← EventDomainServiceTest (regles pures, sans base)
│       ├── Dto/                ← FaqWriteDtoTest, UserCreateDtoTest
│       └── Services/           ← EventServiceTest, RegistrationServiceTest (SQLite en memoire, services obtenus du conteneur)
│   └── Shared/                 ← YamlTest
├── Integration/                ← vraies requetes HTTP
│   ├── Controller/             ← EventControllerTest, HealthControllerTest, DocsControllerTest, UserControllerTest,
│   │                             EmailVerificationControllerTest (jeton lu dans mail-*.log), School/CategoryControllerTest
│   └── Core/                   ← RouterTest, Http/{Kernel,Cors,ExceptionRenderer}Test, Log/AccessLogTest
├── Support/                    ← doublures partagees (EchoController, AppRouter, TestContainer, FakeMailer, FixedClock, ReadsConfirmationMail)
├── fixtures/                   ← scripts lances dans un processus a part
└── TestCase.php, HttpTestCase.php, run.php, run.sh
```

### Base de test

`run.sh` remet a zero la base **`dayout_test`** (schema + migrations + seed) puis lance
`php -S` sur un port libre branche sur cette base, avec des logs dans un dossier temporaire.
La base de dev et `storage/logs/` ne sont **jamais** touches.

Autre base de test : `TEST_DB_NAME=... back/tests/run.sh`.

### Ecrire un test

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

### Assertions

- Unitaires : `assertSame`, `assertTrue`/`assertFalse`, `assertCount`, `assertArrayHasKey`, `assertStringContains`, `assertThrows`, `self::payload($response)`
- HTTP : `assertStatus`, `assertError`, `get` / `post` / `request` -> `TestResponse` (`status`, `header()`, `json()`)

### PHP utilise

`php` du PATH, sinon `C:\xampp\php\php.exe` (ou `PHP=/chemin/php`).

---

## 16) Qualite & style

### Analyse statique — PHPStan

```bash
composer stan            # config : back/phpstan.neon
```

- PHPStan en `require-dev` (version epinglee dans `composer.json`)
- Niveau **8** en local (types des parametres et retours, code mort, `match` non exhaustif, valeurs `null` et `false` possibles)
- `phpVersion: 80200` pour refuser toute syntaxe plus recente que le minimum supporte
- CI : niveau 6 (compromis pour la vitesse)
- Seule exception globale : le type des valeurs de tableaux (`array<string, mixed>`), trop verbeux pour des lignes SQL et du JSON
- Ignorer une ligne precise : `// @phpstan-ignore <identifiant> (raison)`

### Linter de base

```bash
php -l src/Controller/EventController.php    # verifie la syntaxe
```

### Conventions

- **Classes** : `PascalCase`
- **Methodes / variables** : `camelCase`
- **Constantes** : `UPPER_SNAKE_CASE`
- **Noms publics API** : `snake_case` (geres par les DTO)
- **Declare types** : `declare(strict_types=1);` en tete de chaque fichier
- **Typage** : `final readonly class` pour les DTO, `final class` ailleurs sauf si extension prevue

### Statique ou instance

- **statique** pour les fonctions pures sans etat : `Validator`, `Grammar`, `Compiler`, `Caster`, `Rows`, `ValueBinder`, `Password`, `SecurityHeaders`, `ExceptionRenderer`, constructeurs nommes de `HttpException`
- **instance** pour ce qui porte un etat par requete ou des entrees/sorties : `Request`, `Response`, `Router`, `Kernel`, `Connection`, middlewares, controllers, services, models
- **injection par constructeur** : controllers, services et models declarent leurs dependances, `App\Core\Container` les construit ; jamais de `new` d'un service ou d'un model dans le code applicatif
- **facade statique configuree une fois** : `Log` uniquement ; il ne lit pas les superglobales, `public/index.php` lui passe la requete (`Log::captureRequest($_SERVER)`)

---

## 17) Securite — ce qui est en place

- **PDO** `ERRMODE_EXCEPTION` + `EMULATE_PREPARES=false` + placeholders `?` bindes (anti-SQLi)
- **CORS** whitelist via `CORS_ORIGIN` (plus `*` avec credentials jamais)
- **Headers** : `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy`, `CSP`, envoyes par PHP sur toutes les reponses, erreurs comprises
  (`App\Core\Http\SecurityHeaders`, identiques sous Apache et `php -S`)
- **`.htaccess`** bloque les dotfiles et extensions sensibles (`.env`, `.sql`...)
- **Variables d'env** validees au demarrage par `App\Core\Config`
- **`/docs` et `/openapi.json`** coupes par defaut (`DOCS_ENABLED=false` en prod)
- **Connexion BDD** avec delai 3 s (echec rapide, pas de requete bloquee)
- **`Vary: Origin`** sur toutes les reponses (cache partage sans fuite de CORS entre origines)
- **Jamais de `$e->getMessage()` ou de trace** dans la reponse HTTP (log serveur uniquement)
- **`App\Shared\Password`** : bcrypt cost 12, pret pour l'auth future
- **Logs sanitizes** : les cles sensibles (`password`, `token`, `secret`, `authorization`, `cookie`, `csrf`, `session`, `api_key`...) sont remplacees par `[REDACTED]` ; `DB_PASSWORD` masque partout ; IP uniquement dans l'audit
- **Swagger UI** charge depuis jsDelivr en version epinglee avec SRI (DocsController), sa propre CSP (scripts du CDN + script d'init autorise par son hash)

### Donnees sensibles a ne jamais committer

- `.env` (deja dans `.gitignore`)
- `storage/logs/*.log`
- `vendor/`
- `tools/.cache/`, `tools/composer.phar`

---

## 18) Commandes utiles (dev)

### Demarrer le serveur

```bash
php -S localhost:8000 -t back/public
```

### Appels manuels

```bash
curl http://localhost:8000/health
curl http://localhost:8000/events
curl 'http://localhost:8000/events?limit=5&offset=0'
curl 'http://localhost:8000/events?from=2026-10-19T00:00:00Z'
curl 'http://localhost:8000/events?limit=500'          # 422 VALIDATION_ERROR
curl http://localhost:8000/events/1
curl http://localhost:8000/events/9999                 # 404 EVENT_NOT_FOUND
curl http://localhost:8000/events/abc                  # 404 NOT_FOUND (regex)
```

### Reset BDD (dev)

```bash
../infra/sql/reset_db.sh
```

### Tests

```bash
back/tests/run.sh                 # tout
back/tests/run.sh --unit          # unitaires seuls
back/tests/run.sh --integration   # integration seule
back/tests/run.sh Model/          # tests d'un dossier
```

### Analyse statique

```bash
composer stan
```

### Consulter les logs

```bash
tail -f back/storage/logs/error-$(date +%Y-%m-%d).log
tail -f back/storage/logs/info-$(date +%Y-%m-%d).log
tail -f back/storage/logs/audit-$(date +%Y-%m-%d).log
tail -f back/storage/logs/mail-$(date +%Y-%m-%d).log    # mails envoyes (liens de confirmation)
```

---

## 19) Deploiement (apercu backend)

### Methode cible

Serveur LAMP classique (Apache + PHP 8.2+ + MariaDB) ou PaaS PHP.

### Variables d'environnement requises en prod

```env
APP_DEBUG=false
APP_TIMEZONE=UTC
DOCS_ENABLED=false
DB_HOST=db.interne
DB_PORT=3306
DB_NAME=dayout
DB_USER=dayout_app
DB_PASSWORD=<generer-mot-de-passe-fort>
CORS_ORIGIN=https://app.dayout.example
LOG_LEVEL=info
LOG_PATH=/var/log/dayout
LOG_RETENTION_DAYS=30
AUDIT_RETENTION_DAYS=365
APP_ENV=prod
FRONT_URL=https://app.dayout.example
MAIL_DEMO=false
TRUSTED_PROXIES=127.0.0.1
RATE_LIMIT_SECRET=<openssl-rand-hex-32>
```

### Checklist avant mise en prod

- [ ] `.env` genere, **jamais** committer
- [ ] `DOCS_ENABLED=false` (coupe `/docs` et `/openapi.json`)
- [ ] `TRUSTED_PROXIES` = adresse du reverse proxy (sinon tous les clients partagent les limites de debit)
- [ ] `RATE_LIMIT_SECRET` genere (`openssl rand -hex 32`) ; l'API refuse de demarrer sans en prod
- [ ] `APP_DEBUG=false`
- [ ] `CORS_ORIGIN` ne contient que les origines prod
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] Permissions sur `storage/logs/` : ecriture par l'utilisateur Apache/PHP
- [ ] `public/` est le **DocumentRoot** (jamais `back/` directement : `.env` est au niveau `back/`)
- [ ] MariaDB : utilisateur dedie, droits limites a la base `dayout`
- [ ] Headers HTTPS (HSTS, Strict-Transport-Security) ajoutes par le reverse proxy

---

## 20) Troubleshooting

### `vendor/autoload.php not found`

Composer n'a pas ete lance :

```bash
cd back && composer install
# ou si composer n'est pas dans le PATH :
php tools/composer.phar install
```

### `.env` manquant / variable manquante

Le demarrage echoue avec un 500 et la cause dans `storage/logs/error-*.log` :

```bash
cp back/.env.example back/.env
# Editer .env et completer les variables DB_*
```

### MariaDB injoignable

Erreur `Could not connect to database` au bout de 3 secondes :

1. Verifier que MariaDB tourne : XAMPP Control Panel -> *Start* MySQL
2. Verifier `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` dans `.env`
3. Tester la connexion :
   ```bash
   mysql -h 127.0.0.1 -P 3306 -u root dayout
   ```

### Port 8000 deja utilise

**Windows** :

```bash
netstat -ano | findstr :8000
taskkill /PID <PID> /F
```

**Linux / macOS** :

```bash
lsof -ti:8000 | xargs kill -9
```

### Le front est bloque par CORS

Verifier que `CORS_ORIGIN` dans `back/.env` contient bien l'origine du front
(ex. `http://localhost:5500,http://127.0.0.1:5500`) puis redemarrer `php -S`.

### Un test d'integration echoue avec une erreur de schema

La base `dayout_test` est peut-etre dans un etat intermediaire. Forcer le reset :

```bash
back/tests/run.sh --integration        # remet dayout_test a zero avant de lancer
```

### `OpenApiContractTest` echoue

La spec OpenAPI et le code ont divergue. Causes frequentes :

- Route ajoutee dans `routes/<ressource>.php` sans entree dans `docs/openapi/<ressource>.yaml`
- Champ ajoute a `EventDto` / `CategoryDto` sans update du schema YAML
- Fichier de routes sans fichier YAML correspondant

Verifier `tests/Unit/Docs/OpenApiContractTest.php` : il indique exactement ce qui manque.

### Diagnostic general

1. **Logs applicatifs** : `tail -f back/storage/logs/error-$(date +%Y-%m-%d).log`
2. **Health** : `curl http://localhost:8000/health` doit repondre `db: up`
3. **Config** : relancer `php -S` et surveiller la sortie (les erreurs de demarrage sortent la)

---

## 21) Ajouter une route (checklist complete)

1. **DAO** dans `src/Model/Dao/<Ressource>/` (herite de `App\Model\Dao\Dao`, voir section 11), avec ses
   `XxxSearchParams` a cote s'il a une recherche filtree (champ `PageRequest $page`, liste via `paginate()`
   et un `SORT_MAP` public ; `Query/XxxFindQuery` la lit avec `pageRules()` / `page()`),
   avec une methode nommee par besoin metier (`findPublishedById`, pas `find` public).
   Statuts et roles : un enum dans `src/Model/Enums/`, jamais de chaine en dur.
2. **Service** dans `src/Model/Services/` : orchestre, leve des exceptions metier
   (`throw UserException::notFound($id)`), renvoie des DTO. Droits dans `Policies/XxxPolicy`,
   regles sans base dans `DomainServices/XxxDomainService` (testables sans SQLite), relations a charger dans `Assemblers/`.
   Dependances par le constructeur (resolues par le `Container`), jamais de `new`.
3. **DTO** dans `src/Model/Dto/<Ressource>/` (ex. `Dto/Event/`) (`final readonly class ... implements JsonSerializable` :
   proprietes en camelCase, `jsonSerialize()` donne les noms publics en snake_case).
4. **Mapper** dans `src/Model/Mappers/` (`toDto(array $row)`, `toDtos(array $rows)`) ;
   pour une ressource ecrite, `XxxCreateDto` (`RULES`) / `XxxUpdateDto` (`rules()` = `Validator::forUpdate(XxxCreateDto::RULES)`) et `fromArray($json)`, et `toRow` / `toUpdateRow` dans le mapper, construits sur une seule liste `columns()`.
5. **Controller** dans `src/Controller/` (herite de `App\Core\Controller`) ;
   recoit son service par le constructeur, logique HTTP seulement (`$this->idParam()` pour un `{id}`),
   **renvoie** une `Response`. Actions nommees comme le service (`findMany`, `findOne`, `create`...).
6. **Documentation** dans `docs/openapi/<ressource>.yaml` (sinon `OpenApiContractTest` echoue).
7. **Routes** dans `routes/<ressource>.php` (ex. `routes/users.php`), charge automatiquement par `routes/api.php`.
8. **Middlewares** (optionnels) par route ou par groupe (`Authenticate::class`, `RequireRole('admin')`...).
9. **Tests** : au moins un test d'integration qui exerce le succes et les erreurs attendues.

---

## Ressources complementaires

- **Documentation API interactive** : `http://localhost:8000/docs` (si `DOCS_ENABLED=true`)
- **OpenAPI brute** : `http://localhost:8000/openapi.json`
- **Infra & migrations** : [`../infra/README.md`](../infra/README.md)
- **Front** : [`../front/README.md`](../front/README.md)

---

## Licence

Projet academique ISEP A1 — equipe listee dans le [README racine](../README.md).
