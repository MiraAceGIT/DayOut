# DayOut

Plateforme etudiante d'evenements inter-ecoles. Projet web A1.

## Stack

- **Front** : HTML / CSS / JS vanilla (pas de framework, pas de build)
- **Back** : API REST en PHP vanilla + PDO (pas de Composer)
- **BDD**  : MariaDB (via XAMPP)

Front et back sont deux applications separees : le front consomme l'API via
`fetch()`.

## Structure

```
dayout/
├── front/          ← app statique HTML/CSS/JS      → voir front/README.md
├── back/           ← API REST PHP (MVC) + tests     → voir back/README.md
├── infra/
│   ├── sql/        ← schema, migrations, seed      → voir infra/README.md
│   └── README.md
└── docs/           ← MCD-MLD, cahier des charges, fonctionnalites (non versionne)
```

## Prerequis

- **PHP** >= 8.2 (teste sur 8.2 et 8.5)
- **MariaDB** 10.11+ (ou MySQL 8+) demarre sur `localhost:3306`
- **Node.js** 22+ (uniquement pour lancer les tests front)
- **Git Bash** sous Windows (les scripts `*.sh` sont en Bash)

### Installation Linux (Debian / Ubuntu)

```bash
sudo apt update
sudo apt install -y php php-mysql php-mbstring mariadb-server nodejs npm git
sudo systemctl enable --now mariadb
```

### Installation Windows

Via [winget](https://learn.microsoft.com/windows/package-manager/winget/) (PowerShell) :

```powershell
winget install --id PHP.PHP.8.4 -e
winget install --id MariaDB.Server -e
winget install --id OpenJS.NodeJS.LTS -e
winget install --id Git.Git -e
```

Alternative tout-en-un : installer [XAMPP](https://www.apachefriends.org/) (fournit PHP + MariaDB).
Si `php` n'est pas dans le PATH apres l'install XAMPP, utiliser `/c/xampp/php/php.exe` dans Git Bash.

> Redemarrer le terminal apres l'installation pour que les commandes `php`, `node` et `git` soient disponibles.

## Demarrage rapide

Les commandes sont pour Git Bash, depuis la racine du projet.

1. **BDD** : creer la base `dayout` avec le schema et le seed (voir `infra/README.md`) :
   ```bash
   ./infra/sql/reset_db.sh
   ```
   Ensuite, apres chaque `git pull` qui amene des migrations : `./infra/sql/migrate.sh`
   (la base garde ses donnees).
2. **Back** : copier `back/.env.example` en `back/.env`, puis
   ```bash
   php -S localhost:8000 -t back/public
   ```
   Verifier : `curl http://localhost:8000/health` doit repondre `"db":"up"`.
3. **Front** (autre terminal) :
   ```bash
   php -S localhost:5500 -t front
   ```
   Ouvrir `http://localhost:5500`.
4. **Tests** du back (base `dayout_test`, jamais celle de dev) :
   ```bash
   back/tests/run.sh
   ```

## Documentation

- API, architecture du back, tests : [`back/README.md`](back/README.md)
- Front et pages : [`front/README.md`](front/README.md)
- Base de donnees, seed et codes d'erreur des triggers : [`infra/README.md`](infra/README.md)

## Qualite et CI

A chaque push sur `dev` / `main` et a chaque PR, `.github/workflows/ci.yml` lance :

| Job      | Verifie                                                                                     |
| -------- | ------------------------------------------------------------------------------------------- |
| Back     | `php -l`, tests unitaires + integration (MariaDB 10.11) sur PHP 8.2 et 8.5, PHPStan niveau 6 |
| Front    | `node --test` (dont le contrat avec la spec OpenAPI du back), types JSDoc (`tsc`)           |
| Commits  | (PR) chaque message suit la convention ci-dessous                                            |

Les memes commandes en local :

```bash
back/tests/run.sh                          # tests back (base dayout_test)
back/tools/phpstan.sh                      # analyse statique back
cd front && node --test "tests/**/*.test.js"   # tests front (Node 22)
front/tools/typecheck.sh                   # types JSDoc du front
```

## Conventions

- Fins de ligne LF partout (imposees par `.gitattributes`) ; indentation et encodage par
  `.editorconfig` (VS Code : extension *EditorConfig* ; JetBrains : natif).
- Commentaires et documentation en francais ; messages de l'API, codes et noms en anglais.

## Convention de commit

Les messages de commit doivent suivre le format `type(scope optionnel): description`.

Types acceptes : `feat`, `fix`, `refacto`, `refactor`, `docs`, `style`, `test`, `chore`, `perf`, `build`, `ci`, `revert`.

Exemples : `feat: add login page`, `fix(api): handle null user`, `refacto(front): split header`.

Activer les hooks (une fois apres le clone) : `commit-msg` verifie ce format, `post-merge`
signale les nouvelles migrations de BDD apres un `git pull` (`./infra/sql/migrate.sh`).

```bash
git config core.hooksPath .githooks
```
