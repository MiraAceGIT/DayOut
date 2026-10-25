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
│   ├── sql/        ← schema, seed, reset_db.sh     → voir infra/README.md
│   └── README.md
└── docs/           ← MCD-MLD, cahier des charges, fonctionnalites (non versionne)
```

## Demarrage rapide

Prerequis : XAMPP avec MySQL demarre. Les commandes sont pour Git Bash, depuis la
racine du projet. Si `php` n'est pas dans le PATH, utiliser `/c/xampp/php/php.exe`.

1. **BDD** : creer la base `dayout` avec le schema et le seed (voir `infra/README.md`) :
   ```bash
   ./infra/sql/reset_db.sh
   ```
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

## Conventions

- Fins de ligne LF partout (imposees par `.gitattributes`).
- Commentaires et documentation en francais ; messages de l'API, codes et noms en anglais.

## Convention de commit

Les messages de commit doivent suivre le format `type(scope optionnel): description`.

Types acceptes : `feat`, `fix`, `refacto`, `refactor`, `docs`, `style`, `test`, `chore`, `perf`, `build`, `ci`, `revert`.

Exemples : `feat: add login page`, `fix(api): handle null user`, `refacto(front): split header`.

Activer le hook (une fois apres le clone) :

```bash
git config core.hooksPath .githooks
```
