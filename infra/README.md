# Infra — Base de données DayOut (XAMPP / MariaDB)

Installation locale de la base DayOut via **XAMPP** (Apache + MariaDB + PHP + phpMyAdmin).

Les scripts SQL sont dans `infra/sql/` et doivent être joués **dans l'ordre** sur une base `dayout` fraîche.

## Prérequis

- [XAMPP](https://www.apachefriends.org/fr/index.html) ≥ 8.0 (fournit MariaDB 10.4 ou supérieur, compatible avec les scripts)
- Depuis le panneau XAMPP : démarrer **Apache** + **MySQL** (bouton *Start*)

## Installation en 4 étapes

### 1. Créer la base de données

Ouvrir http://localhost/phpmyadmin dans un navigateur, puis :

1. Cliquer sur l'onglet **Bases de données** (ou *Databases*)
2. Créer une base nommée `dayout`
3. Interclassement : `utf8mb4_unicode_ci`
4. Cliquer sur *Créer*

### 2. Importer le schéma (`01_schema.sql`)

1. Dans phpMyAdmin, sélectionner la base `dayout` à gauche
2. Cliquer sur l'onglet **Importer**
3. Choisir le fichier `infra/sql/01_schema.sql`
4. Cliquer sur *Exécuter* (en bas)

Résultat attendu : 21 tables + 1 vue créées, triggers métier installés.

### 3. Importer le jeu de données (`02_seed.sql`)

Même procédure, avec `infra/sql/02_seed.sql`.

Résultat attendu : 15 écoles, 14 utilisateurs, 10 événements, 16 inscriptions, etc.

### 4. Vérifier

Dans phpMyAdmin → base `dayout` → onglet **SQL**, coller :

```sql
SELECT 'users' AS t, COUNT(*) AS c FROM users
UNION SELECT 'events',  COUNT(*) FROM events
UNION SELECT 'schools', COUNT(*) FROM schools
UNION SELECT 'registrations', COUNT(*) FROM registrations;
```

Attendu : `users=14`, `events=10`, `schools=15`, `registrations=16`.

## Alternative : ligne de commande (plus rapide)

Si tu as un terminal avec `mysql` dans le PATH (dans Git Bash ou PowerShell) :

```bash
cd infra/sql
mysql -u root -e "CREATE DATABASE dayout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root dayout < 01_schema.sql
mysql -u root dayout < 02_seed.sql
```

Le binaire `mysql` est fourni par XAMPP dans `C:\xampp\mysql\bin\mysql.exe`.

## Reset complet

Pour repartir d'une base vide (après avoir modifié un script par exemple) :

Dans phpMyAdmin → base `dayout` → **Opérations** → *Supprimer la base de données* → recommencer les étapes 1 à 3.

Ou en ligne de commande :

```bash
mysql -u root -e "DROP DATABASE dayout; CREATE DATABASE dayout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root dayout < 01_schema.sql
mysql -u root dayout < 02_seed.sql
```

## Comptes de test (après seed)

Mot de passe commun : **`Password!2026`** (hash bcrypt dans le seed).

| Email                              | Rôle                | Note                                |
| ---------------------------------- | ------------------- | ----------------------------------- |
| `admin@dayout.local`               | admin               | accès back-office complet           |
| `bde.isep@isep.fr`                 | créateur approuvé   | BDE ISEP                            |
| `orga.epita@epita.fr`              | créateur approuvé   | Orga EPITA                          |
| `events@epitech.eu`                | créateur approuvé   | Events EPITECH                      |
| `pending@efrei.net`                | créateur en attente | demande à valider par l'admin       |
| `yovanne@isep.fr`                  | participant         | profil nominal                      |
| `risky@esiee.fr`                   | participant         | score fiabilité 30 → inscriptions bloquées |
| `unverified@devinci.fr`            | participant         | email non vérifié → compte inactif  |
| (+ 6 autres participants)          | participant         | voir `02_seed.sql`                  |

## Modifier la liste des écoles autorisées

Les écoles autorisées sont dans la table `schools` (colonne `email_domain`). Un utilisateur ne peut s'inscrire que si le domaine de son email y figure (contrôle par trigger `trg_users_bi_whitelist`).

Pour ajouter une école depuis phpMyAdmin :

```sql
INSERT INTO schools (name, short_name, email_domain, city, active)
VALUES ('Nom complet', 'Alias', 'domaine-email.fr', 'Ville', 1);
```

Pour le seed initial, éditer directement `infra/sql/02_seed.sql` (section 1).

## Connexion depuis l'application PHP

Dans la config PHP (fichier `.env` ou équivalent du back) :

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=dayout
DB_USER=root
DB_PASSWORD=
```

En XAMPP par défaut, `root` n'a **pas** de mot de passe. En environnement partagé ou production, créer un utilisateur dédié avec droits restreints (voir *Pour aller plus loin* ci-dessous).

## Structure des fichiers

```
infra/
├── README.md             ← ce fichier
└── sql/
    ├── 01_schema.sql     ← tables, index, triggers, vue
    └── 02_seed.sql       ← données de test (écoles, users, events…)
```

Documentation du modèle de données : [`docs/MCD-MLD.md`](../docs/MCD-MLD.md).

## Pour aller plus loin (hors scope projet scolaire)

En production, l'application PHP ne doit **jamais** se connecter avec `root`. Créer plutôt un utilisateur MariaDB dédié avec le strict minimum de droits (SELECT/INSERT/UPDATE/DELETE sur les tables métier, SELECT seul sur les référentiels). Exemple :

```sql
CREATE USER 'dayout_app'@'localhost' IDENTIFIED BY 'un_mot_de_passe_fort';
GRANT SELECT, INSERT, UPDATE, DELETE ON dayout.* TO 'dayout_app'@'localhost';
FLUSH PRIVILEGES;
```
