# DayOut

Plateforme etudiante d'evenements inter-ecoles. Projet web A1.

## 1) Vue d'ensemble

### Que fait l'application ?

**DayOut** regroupe les evenements organises par les BDE, assos et services vie etudiante
de plusieurs ecoles d'Ile-de-France : soirees, hackathons, forums, conferences, sport.
L'utilisateur voit les evenements autour de lui (carte + liste) et peut s'inscrire.

### Statut actuel

**V1 publique en lecture seule** — Les evenements et categories sont servis par l'API
(`GET /events`, `GET /events/{id}`), affiches sur la page carte et la liste. L'inscription,
l'authentification, les comptes et l'administration sont en cours (back et front prets a
accueillir les middlewares d'auth et les routes protegees).

### Perimetre

**Inclus** :

- Liste et detail d'evenements publies (categorie, lieu, dates, tarif, places)
- Carte interactive (Leaflet + OpenStreetMap) avec filtres cote client
- Geolocalisation navigateur, rayon reglable, recherche plein texte
- Zone de couverture Ile-de-France (contour geographique exact)
- Doc OpenAPI 3 servie en dev (Swagger UI sur `/docs`)
- Logs structures (JSON par ligne) avec `requestId`, audit separe, rotation
- Tests automatises (unitaires + integration pour le back, unitaires + contrat pour le front)

**Exclu (encore)** :

- Authentification et inscriptions aux evenements (schema BDD pret)
- Creation d'evenements cote utilisateur (DTO d'entree + validation prets)
- Notifications push / emails
- Interface d'administration

## 2) Stack

- **Front** : HTML / CSS / JS vanilla (modules ES natifs, pas de build, pas de npm)
- **Back**  : PHP 8.2+ vanilla (Composer uniquement pour autoload PSR-4 + `firebase/php-jwt`)
- **BDD**   : MariaDB 10.11+ (fournie par XAMPP en local)
- **Carto** : Leaflet 1.9.4 (UMD + SRI) sur tuiles OpenStreetMap

Front et back sont deux applications separees : le front consomme l'API via `fetch()`.

## 3) Structure du depot

```
dayout/
├── front/            ← app statique HTML/CSS/JS         → front/README.md
├── back/             ← API REST PHP (MVC) + tests       → back/README.md
├── infra/
│   ├── sql/          ← schema, migrations, seed, triggers
│   └── README.md     ← installation BDD, codes trigger  → infra/README.md
├── docs/             ← MCD-MLD, cahier des charges, fonctionnalites (non versionne)
├── .githooks/        ← commit-msg (format conventionnel), post-merge (migrations)
├── .github/
│   └── workflows/
│       └── ci.yml    ← tests back (PHP 8.2/8.5), tests front (Node 22), PHPStan 6
└── README.md         ← ce fichier
```

## 4) Prerequis & versions

| Outil        | Version    | Role                                              |
| ------------ | ---------- | ------------------------------------------------- |
| **PHP**      | >= 8.2     | Backend (teste sur 8.2 et 8.5)                    |
| **MariaDB**  | >= 10.11   | Base de donnees (MySQL 8+ compatible)             |
| **Composer** | >= 2.5     | Autoload + deps (telecharge via `tools/composer.phar` au besoin) |
| **Node.js**  | >= 22      | Runner de tests front (`node:test`)               |
| **Git Bash** | n/a        | Les scripts `*.sh` sont en Bash                   |

### Installation rapide

**Linux (Debian / Ubuntu)** :

```bash
sudo apt update
sudo apt install -y php php-mysql php-mbstring mariadb-server nodejs npm git
sudo systemctl enable --now mariadb
```

**Windows (winget)** :

```powershell
winget install --id PHP.PHP.8.4 -e
winget install --id MariaDB.Server -e
winget install --id OpenJS.NodeJS.LTS -e
winget install --id Git.Git -e
```

Alternative tout-en-un : installer [XAMPP](https://www.apachefriends.org/) (PHP + MariaDB + phpMyAdmin).
Si `php` n'est pas dans le PATH apres XAMPP, utiliser `/c/xampp/php/php.exe` sous Git Bash.

## 5) Demarrage rapide

Toutes les commandes sont pour Git Bash, depuis la racine du projet.

### 1. Base de donnees

```bash
./infra/sql/reset_db.sh      # cree la base `dayout`, applique schema + migrations + seed
```

Apres chaque `git pull` qui amene des migrations : `./infra/sql/migrate.sh`
(la base garde ses donnees). Voir `infra/README.md`.

### 2. Backend

```bash
cp back/.env.example back/.env
cd back && composer install && cd ..
php -S localhost:8000 -t back/public
```

Verifier : `curl http://localhost:8000/health` repond `{"data":{"api":"up","db":"up","time":"..."}}`.

### 3. Frontend (autre terminal)

```bash
php -S localhost:5500 -t front
```

Ouvrir `http://localhost:5500`.

### 4. Tests

```bash
back/tests/run.sh                                 # back : unitaires + integration (base `dayout_test`)
cd front && node --test "tests/**/*.test.js"      # front : Node 22, aucune dependance
```

## 6) Documentation detaillee

| Document                          | Contenu                                                                                 |
| --------------------------------- | --------------------------------------------------------------------------------------- |
| [`back/README.md`](back/README.md)   | API, architecture MVC du back, ORM maison, tests, OpenAPI, securite, erreurs             |
| [`front/README.md`](front/README.md) | Pages, architecture MVC du front, client API, carte Leaflet, charte, accessibilite       |
| [`infra/README.md`](infra/README.md) | Installation BDD (XAMPP + phpMyAdmin), migrations, seed, codes d'erreur des triggers     |

Doc API interactive (Swagger UI) : `http://localhost:8000/docs` quand `DOCS_ENABLED=true`.

## 7) Qualite et integration continue

A chaque push sur `dev` / `main` et a chaque PR, `.github/workflows/ci.yml` lance :

| Job      | Verifie                                                                                      |
| -------- | -------------------------------------------------------------------------------------------- |
| Back     | `php -l`, tests unitaires + integration (MariaDB 10.11) sur PHP 8.2 et 8.5, PHPStan niveau 6 |
| Front    | `node --test` (dont le contrat avec la spec OpenAPI du back), types JSDoc via `tsc`          |
| Commits  | (PR) chaque message suit la convention `type(scope): description`                            |

Les memes commandes en local :

```bash
back/tests/run.sh                                 # tests back
cd back && composer stan                          # PHPStan niveau 8 (plus strict qu'en CI)
cd front && node --test "tests/**/*.test.js"      # tests front
front/tools/typecheck.sh                          # types JSDoc du front
```

## 8) Conventions

### Code

- Fins de ligne **LF** partout (imposees par `.gitattributes`)
- Indentation et encodage par `.editorconfig` (VS Code : extension *EditorConfig* ; JetBrains : natif)
- Commentaires et documentation **en francais** (sans accents dans le code PHP et le SQL)
- Messages de l'API, codes d'erreur et noms de symboles **en anglais**

### Commits (hook `commit-msg`)

Format `type(scope optionnel): description`. Types acceptes :
`feat`, `fix`, `refacto`, `refactor`, `docs`, `style`, `test`, `chore`, `perf`, `build`, `ci`, `revert`.

Exemples : `feat: add login page`, `fix(api): handle null user`, `refacto(front): split header`.

Activer les hooks (une fois apres le clone) :

```bash
git config core.hooksPath .githooks
```

Le hook `post-merge` signale les nouvelles migrations apres `git pull` et rappelle de lancer
`./infra/sql/migrate.sh`.

## 9) Equipe

Projet etudiant A1 ISEP :

- PONNOU Yovanne
- MAZUR Jakub
- CHOULET Anthony
- PAILLARD Julia
- GOUARAND Marie-Jeanne
- GUEDDOU Bilal

## 10) Licence

Projet academique, usage educatif uniquement.
