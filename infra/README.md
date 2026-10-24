# Infra — Base de données DayOut (XAMPP / MariaDB)

Installation locale de la base DayOut via **XAMPP** (Apache + MariaDB + PHP + phpMyAdmin).

Le schéma est découpé par domaine dans `infra/sql/schema/` (un fichier par groupe de tables, triggers à part). Les fichiers sont numérotés et doivent être joués **dans l'ordre** sur une base `dayout` fraîche.

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

### 2. Importer le schéma

phpMyAdmin n'importe qu'un fichier à la fois : on regroupe d'abord les fichiers de `schema/` en un seul (depuis Git Bash) :

```bash
./infra/sql/build_schema.sh   # génère infra/sql/dist/schema.sql (non versionné)
```

Puis :

1. Dans phpMyAdmin, sélectionner la base `dayout` à gauche
2. Cliquer sur l'onglet **Importer**
3. Choisir le fichier `infra/sql/dist/schema.sql`
4. Cliquer sur *Exécuter* (en bas)

> Après toute modification d'un fichier de `schema/`, relancer `build_schema.sh` avant de réimporter.

Résultat attendu : 21 tables + 1 vue créées, triggers métier installés.

### 3. Importer le jeu de données (`seed.sql`)

Même procédure, avec `infra/sql/seed.sql`.

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
cat schema/*.sql | mysql -u root dayout
mysql -u root dayout < seed.sql
```

Le binaire `mysql` est fourni par XAMPP dans `C:\xampp\mysql\bin\mysql.exe`.

## Reset complet

Pour repartir d'une base vide (après avoir modifié un script par exemple) :

Dans phpMyAdmin → base `dayout` → **Opérations** → *Supprimer la base de données* → recommencer les étapes 1 à 3.

Ou en ligne de commande :

```bash
mysql -u root -e "DROP DATABASE dayout; CREATE DATABASE dayout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cat schema/*.sql | mysql -u root dayout
mysql -u root dayout < seed.sql
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
| (+ 6 autres participants)          | participant         | voir `seed.sql`                     |

## Modifier la liste des écoles autorisées

Les écoles autorisées sont dans la table `schools` (colonne `email_domain`). Un utilisateur ne peut s'inscrire que si le domaine de son email y figure (contrôle par trigger `trg_users_bi_whitelist`).

Pour ajouter une école depuis phpMyAdmin :

```sql
INSERT INTO schools (name, short_name, email_domain, city, active)
VALUES ('Nom complet', 'Alias', 'domaine-email.fr', 'Ville', 1);
```

Pour le seed initial, éditer directement `infra/sql/seed.sql` (section 1).

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
    ├── schema/
    │   ├── 00_reset.sql                  ← paramètres de session + DROP (rejouable)
    │   ├── 01_referentiels.sql           ← schools, categories
    │   ├── 02_users.sql                  ← users, email_verifications, creator_profiles, user_interests
    │   ├── 03_events.sql                 ← events, event_categories
    │   ├── 04_registrations.sql          ← registrations, paid_event_clicks, attendance_events, absence_corrections
    │   ├── 05_planning.sql               ← plannings, planning_items, suggestion_dismissals
    │   ├── 06_moderation.sql             ← event_messages, reports, report_actions
    │   ├── 07_support.sql                ← faq_entries, legal_pages, contact_messages
    │   ├── 08_views.sql                  ← v_user_reliability
    │   ├── 10_triggers_users.sql         ← whitelist écoles
    │   ├── 11_triggers_events.sql        ← cohérence restriction école
    │   ├── 12_triggers_registrations.sql ← contrôles d'inscription + registered_count
    │   ├── 13_triggers_admin_roles.sql   ← FK vers users réservées aux admins
    │   └── 14_triggers_fk_checks.sql     ← règles impossibles en CHECK sur une FK (MariaDB #1901)
    ├── build_schema.sh   ← concatène schema/*.sql → dist/schema.sql
    └── seed.sql          ← données de test (écoles, users, events…)

Ajouter une table : la placer dans le fichier de son domaine (en respectant l'ordre des FK) et ajouter son `DROP TABLE IF EXISTS` dans `00_reset.sql`.
```

Documentation du modèle de données : [`docs/MCD-MLD.md`](../docs/MCD-MLD.md).

## Pour aller plus loin (hors scope projet scolaire)

En production, l'application PHP ne doit **jamais** se connecter avec `root`. Créer plutôt un utilisateur MariaDB dédié avec le strict minimum de droits (SELECT/INSERT/UPDATE/DELETE sur les tables métier, SELECT seul sur les référentiels). Exemple :

```sql
CREATE USER 'dayout_app'@'localhost' IDENTIFIED BY 'un_mot_de_passe_fort';
GRANT SELECT, INSERT, UPDATE, DELETE ON dayout.* TO 'dayout_app'@'localhost';
FLUSH PRIVILEGES;
```
