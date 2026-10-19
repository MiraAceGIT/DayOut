# Infra — Docker (MySQL + Adminer)

Stack locale du projet : une base **MySQL 8.4** + **Adminer** (interface web) orchestrées par Docker Compose.

## Prérequis

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (inclut `docker compose`)
- Ports libres sur la machine : `3306` (MySQL) et `8080` (Adminer) — personnalisables via `.env`

## Première utilisation

1. Se placer dans le dossier `infra/` :
   ```bash
   cd infra
   ```
2. Créer le fichier `.env` à partir du modèle :
   ```bash
   cp .env.example .env
   ```
   Puis adapter les valeurs si besoin (mots de passe, ports…).
3. Démarrer la stack :
   ```bash
   docker compose up -d
   ```

Au premier démarrage, les scripts SQL présents dans `docker/mysql/init/` (ex. `01_schema.sql`, `02_seed.sql`) sont exécutés automatiquement dans l'ordre alphabétique.

## Accès

| Service | URL / Hôte              | Identifiants (par défaut)         |
| ------- | ----------------------- | --------------------------------- |
| MySQL   | `localhost:3306`        | user `app` / mdp `app` — base `app` |
| Root    | `localhost:3306`        | user `root` / mdp `root`          |
| Adminer | http://localhost:8080   | Serveur : `mysql` — user/mdp ci-dessus |

## Commandes utiles

```bash
# Démarrer en arrière-plan
docker compose up -d

# État des conteneurs
docker compose ps

# Logs (suivre en direct)
docker compose logs -f mysql

# Console SQL en root
docker compose exec mysql mysql -u root -p

# Arrêter la stack (les données sont conservées)
docker compose down

# Reset complet : supprime les données + rejoue les scripts d'init
docker compose down -v && docker compose up -d
```

## Variables d'environnement (`.env`)

| Variable               | Rôle                                  | Défaut   |
| ---------------------- | ------------------------------------- | -------- |
| `COMPOSE_PROJECT_NAME` | Préfixe des conteneurs                | `dayout` |
| `MYSQL_ROOT_PASSWORD`  | Mot de passe root                     | `root`   |
| `MYSQL_DATABASE`       | Base créée au 1er démarrage           | `app`    |
| `MYSQL_USER`           | Utilisateur applicatif                | `app`    |
| `MYSQL_PASSWORD`       | Mot de passe applicatif               | `app`    |
| `MYSQL_PORT`           | Port MySQL exposé sur l'hôte          | `3306`   |
| `ADMINER_PORT`         | Port Adminer exposé sur l'hôte        | `8080`   |

## Scripts d'initialisation

Déposer les fichiers `.sql` dans `docker/mysql/init/`. Ils ne sont joués **qu'au premier démarrage** (volume `mysql_data` vide). Pour les rejouer après modification :

```bash
docker compose down -v
docker compose up -d
```

## Dépannage

- **Port déjà utilisé** → changer `MYSQL_PORT` ou `ADMINER_PORT` dans `.env`.
- **Adminer ne se connecte pas** → bien renseigner `mysql` (nom du service) comme serveur, pas `localhost`.
- **Modifs des scripts SQL ignorées** → faire un `docker compose down -v` pour repartir d'une base vide.
