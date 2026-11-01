# Infra — Base de données DayOut (XAMPP / MariaDB)

Installation locale de la base DayOut via **XAMPP** (Apache + MariaDB + PHP + phpMyAdmin).

Le schéma de départ est découpé par domaine dans `infra/sql/schema/` (un fichier par groupe de tables, triggers à part). Il est **gelé** : toute modification de la base passe ensuite par un fichier de `infra/sql/migrations/`, appliqué sur chaque environnement par `migrate.sh` (voir *Migrations*).

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

phpMyAdmin n'importe qu'un fichier à la fois : on regroupe d'abord `schema/` et `migrations/` en un seul (depuis Git Bash) :

```bash
./infra/sql/build_schema.sh   # génère infra/sql/dist/schema.sql (non versionné)
```

Puis :

1. Dans phpMyAdmin, sélectionner la base `dayout` à gauche
2. Cliquer sur l'onglet **Importer**
3. Choisir le fichier `infra/sql/dist/schema.sql`
4. Cliquer sur *Exécuter* (en bas)

> Le fichier généré remplit aussi le journal `schema_migrations` : par la suite, `./infra/sql/migrate.sh` suffit à appliquer les nouvelles migrations.

Résultat attendu : 21 tables + 1 vue créées, 13 fonctions/procédures (`09_routines.sql`) et 22 triggers métier installés.

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

Cohérence des compteurs d'inscrits (doit renvoyer 0 ligne) :

```sql
SELECT e.id, e.registered_count, COUNT(r.id) AS reel
FROM events e
LEFT JOIN registrations r ON r.event_id = e.id AND r.status <> 'cancelled'
GROUP BY e.id, e.registered_count
HAVING e.registered_count <> COUNT(r.id);
```

## Alternative : ligne de commande (plus rapide)

Si tu as un terminal avec `mysql` dans le PATH (dans Git Bash ou PowerShell) :

```bash
mysql -u root -e "CREATE DATABASE dayout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
./infra/sql/migrate.sh                       # schema/ puis migrations/
mysql -u root dayout < infra/sql/seed.sql
```

Le binaire `mysql` est fourni par XAMPP dans `C:\xampp\mysql\bin\mysql.exe`.

## Reset complet

Pour repartir d'une base propre (seed modifié, base locale incohérente...), depuis Git Bash :

```bash
./infra/sql/reset_db.sh                # base de back/.env (dayout), demande confirmation
./infra/sql/reset_db.sh dayout_test    # une autre base (utilisée par back/tests/run.sh)
./infra/sql/reset_db.sh --yes          # sans confirmation
./infra/sql/reset_db.sh --no-seed      # schéma seul
```

Le script supprime et recrée la base, la monte à jour avec `migrate.sh` (`schema/` puis `migrations/`), joue `seed.sql`, et
affiche les comptages attendus. Connexion lue dans `back/.env` (`DB_HOST`, `DB_PORT`,
`DB_USER`, `DB_PASSWORD`). Client : `mysql` du PATH, sinon `C:\xampp\mysql\bin\mysql.exe`
(ou `MYSQL=/chemin/mysql`). MySQL doit être démarré dans XAMPP.

Un fichier `.env` absent, ou une clé manquante, n'est pas une erreur : la valeur par défaut
s'applique (`127.0.0.1`, `3306`, `root` sans mot de passe, `dayout`). Pour lire un autre
fichier : `ENV_FILE=/chemin/vers/.env ./infra/sql/reset_db.sh --yes dayout_test`.

À la main, dans phpMyAdmin → base `dayout` → **Opérations** → *Supprimer la base de données* → recommencer les étapes 1 à 3.

Ou en ligne de commande :

```bash
mysql -u root -e "DROP DATABASE dayout; CREATE DATABASE dayout CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
./infra/sql/migrate.sh
mysql -u root dayout < infra/sql/seed.sql
```

## Migrations

Une modification de la base (table, colonne, index, trigger, données à corriger) est un
**fichier de migration**, jamais une édition de `schema/` : les bases existantes (la tienne,
celles des autres, `dayout_test`, la CI, une future prod) ne rejouent pas `schema/`, elles
n'appliquent que les migrations qui leur manquent.

### Écrire une migration

```bash
./infra/sql/migrate.sh --new add_event_tagline
# -> infra/sql/migrations/20261031193000_add_event_tagline.sql (horodatage UTC)
```

```sql
-- add_event_tagline
ALTER TABLE events ADD COLUMN tagline VARCHAR(160) NULL AFTER title;
UPDATE events SET tagline = LEFT(description, 160) WHERE tagline IS NULL;
```

- SQL MariaDB normal, `DELIMITER $` autorisé pour un trigger ou une procédure (remplacer un
  trigger : `DROP TRIGGER IF EXISTS ...` puis `CREATE TRIGGER`, dans la même migration).
- Session identique à `schema/` : UTC, `sql_mode` strict, utf8mb4.
- Penser aux lignes existantes : une colonne `NOT NULL` ajoutée sur une table remplie prend un
  `DEFAULT` ou une mise à jour juste après.
- Mettre à jour `seed.sql` si besoin : il est joué après toutes les migrations.
- Nouvelle règle de trigger : son `errno` va aussi dans le tableau *Codes d'erreur des triggers*.
- Tester : `./infra/sql/migrate.sh` sur ta base, puis `back/tests/run.sh` (qui recrée
  `dayout_test` en passant par toutes les migrations).

**Une migration mergée ne se modifie plus** : elle a déjà été jouée ailleurs, la changer ne
changerait que les nouvelles bases. Pour corriger, écrire une nouvelle migration. Le
`checksum` enregistré le détecte (`migrate.sh` refuse de continuer) et la CI refuse une PR qui
modifie ou supprime un fichier existant de `schema/` ou de `migrations/`.

### Appliquer

```bash
./infra/sql/migrate.sh --status        # appliquées [x] / en attente [ ], ne modifie rien
./infra/sql/migrate.sh                 # base de back/.env
./infra/sql/migrate.sh dayout_test     # une autre base
```

| Environnement           | Quand                                    | Comment                                      |
| ----------------------- | ---------------------------------------- | -------------------------------------------- |
| Base locale de chacun   | après un `git pull` qui amène des migrations (le hook `post-merge` le signale) | `./infra/sql/migrate.sh` |
| `dayout_test`           | à chaque `back/tests/run.sh`              | recréée par `reset_db.sh`, donc migrée       |
| CI                      | chaque push / PR                         | idem, puis vérifie qu'une 2e passe ne change rien |
| Serveur (si un jour)    | à chaque déploiement, avant le code      | `DB_HOST=... DB_NAME=... ./infra/sql/migrate.sh` |

Le journal est la table `schema_migrations` (`version`, `checksum`, `applied_at`) ;
`schema/` y figure sous la version `00000000000000_baseline`. Sur une base créée avant les
migrations (tables présentes, pas de journal), le premier `migrate.sh` la rattache à
`schema/` sans rien rejouer, puis applique les migrations. Dans le doute sur son état,
`reset_db.sh`.

Une migration en échec n'est pas enregistrée, mais MariaDB n'annule pas le DDL déjà exécuté
(un `ALTER` passé reste) : corriger la base à la main ou, en local, `reset_db.sh`, puis relancer.
Garder des migrations courtes limite ce cas.

## Compteur `events.registered_count`

`registered_count` est une dénormalisation maintenue par triggers (`12_triggers_registrations.sql`).
Une inscription est **active** (comptée) si `status <> 'cancelled'` : `registered`, `attended` et
`no_show` comptent, `cancelled` non. Cette définition n'existe qu'à un endroit,
`fn_registration_is_active()` (`09_routines.sql`), utilisée par tous les triggers.

La place est réservée sous verrou (`SELECT ... FOR UPDATE` sur la ligne `events`) : deux inscriptions
simultanées à la dernière place sont sérialisées, la seconde reçoit `50304 Event is full`.

Après un reset complet (`reset_db.sh`), les compteurs sont toujours justes : le seed passe par les
triggers. Une migration qui change la définition d'une inscription active doit aussi recalculer
`registered_count` sur les lignes existantes.

## Écoles : désactiver, ne jamais supprimer

Une école ne se supprime pas tant qu'elle est référencée : `users.school_id` et
`events.restricted_school_id` sont en `ON DELETE RESTRICT` (un `DELETE` renvoie l'erreur native
`1451`). Pour retirer une école, la **désactiver** :

```sql
UPDATE schools SET active = 0 WHERE id = ?;
```

Effet : plus aucune inscription de compte ni changement d'email/rôle vers ce domaine
(`50101`) ; les comptes et événements existants restent intacts. Un `ON DELETE SET NULL` aurait
rendu public en silence un événement réservé à l'école (et laissé `school_restricted = 1` sans école).

## Codes d'erreur des triggers

Chaque règle métier lève `SIGNAL SQLSTATE '45000'` avec un `MYSQL_ERRNO` dédié (plage 50001-50999,
par domaine : 501xx users, 502xx events, 503xx registrations, 504xx rôles admin, 505xx planning /
modération). Côté PHP, `PDOException::getCode()` vaut `'45000'` pour toutes : lire le code dans
`$e->errorInfo[1]` (et le message dans `$e->errorInfo[2]`).

| errno | message | trigger(s) | HTTP suggéré |
| ----- | ------- | ---------- | ------------ |
| 50101 | `Email domain not in schools whitelist` | `trg_users_bi_whitelist`, `trg_users_bu_whitelist` | 422 |
| 50201 | `school_restricted=1 requires restricted_school_id` | `trg_events_bi`, `trg_events_bu` | 422 |
| 50202 | `school_restricted=0 forbids restricted_school_id` | `trg_events_bi`, `trg_events_bu` | 422 |
| 50301 | `User is banned` | `trg_registrations_bi`, `trg_registrations_bu` | 403 |
| 50302 | `Reliability score too low (<40) to register` | `trg_registrations_bi`, `trg_registrations_bu` | 403 |
| 50303 | `Event is not open for registration` | `trg_registrations_bi`, `trg_registrations_bu` | 409 |
| 50304 | `Event is full` | `trg_registrations_bi`, `trg_registrations_bu` | 409 |
| 50401 | `report_actions.admin_id must reference a user with role=admin` | `trg_report_actions_bi`, `trg_report_actions_bu` | 403 |
| 50402 | `absence_corrections.reviewed_by must reference a user with role=admin` | `trg_absence_corr_bi`, `trg_absence_corr_bu` | 403 |
| 50403 | `creator_profiles.reviewed_by must reference a user with role=admin` | `trg_creator_profiles_bi`, `trg_creator_profiles_bu` | 403 |
| 50404 | `contact_messages.handled_by must reference a user with role=admin` | `trg_contact_msg_bi`, `trg_contact_msg_bu` | 403 |
| 50501 | `planning_items requires event_id or title` | `trg_planning_items_bi`, `trg_planning_items_bu` | 422 |
| 50502 | `reports target_*_id does not match target_type` | `trg_reports_bi`, `trg_reports_bu` | 422 |

Les règles sont écrites dans `09_routines.sql` (procédures `sp_assert_*`, `sp_lock_event_seat`), les
triggers ne font que les appeler. Erreurs natives MariaDB utiles à mapper aussi :
`1062` doublon (ex. déjà inscrit, `uk_reg_event_user`) → 409, `1451` suppression d'une ligne
référencée (ex. école) → 409, `1452` FK vers une ligne inexistante → 422, `4025` CHECK violé → 422, `1406` chaîne plus longue
que la colonne → 422, `1264` nombre hors du type de la colonne → 422.

Ajouter une règle : prendre le prochain numéro libre dans la plage du domaine, l'ajouter à ce tableau.

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

Les écoles autorisées sont dans la table `schools` (colonne `email_domain`). Un utilisateur ne peut s'inscrire que si le domaine de son email y figure (contrôle par trigger `trg_users_bi_whitelist`, rejoué par `trg_users_bu_whitelist` quand l'email **ou le rôle** change : un admin hors whitelist ne peut pas être rétrogradé ; si le domaine change, `school_id` est recalculé).

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
    ├── schema/           ← schéma de départ, gelé (modifications : migrations/)
    │   ├── 00_reset.sql                  ← paramètres de session + DROP (rejouable)
    │   ├── 01_referentiels.sql           ← schools, categories
    │   ├── 02_users.sql                  ← users, email_verifications, creator_profiles, user_interests
    │   ├── 03_events.sql                 ← events, event_categories
    │   ├── 04_registrations.sql          ← registrations, paid_event_clicks, attendance_events, absence_corrections
    │   ├── 05_planning.sql               ← plannings, planning_items, suggestion_dismissals
    │   ├── 06_moderation.sql             ← event_messages, reports, report_actions
    │   ├── 07_support.sql                ← faq_entries, legal_pages, contact_messages
    │   ├── 08_views.sql                  ← v_user_reliability
    │   ├── 09_routines.sql               ← fonctions/procédures partagées par les triggers (règles + codes d'erreur)
    │   ├── 10_triggers_users.sql         ← whitelist écoles
    │   ├── 11_triggers_events.sql        ← cohérence restriction école
    │   ├── 12_triggers_registrations.sql ← contrôles d'inscription + registered_count
    │   ├── 13_triggers_admin_roles.sql   ← FK vers users réservées aux admins
    │   └── 14_triggers_fk_checks.sql     ← règles impossibles en CHECK sur une FK (MariaDB #1901)
    ├── migrations/       ← une modification de la base par fichier (AAAAMMJJHHMMSS_nom.sql)
    ├── migrate.sh        ← applique schema/ puis les migrations manquantes (journal schema_migrations)
    ├── build_schema.sh   ← concatène schema/ + migrations/ → dist/schema.sql (phpMyAdmin)
    ├── reset_db.sh       ← recrée une base, migrate.sh, puis seed, en une commande
    ├── lib.sh            ← connexion BDD et journal, partagés par les scripts
    └── seed.sql          ← données de test (écoles, users, events…)

Ajouter une table, une colonne, une fonction ou un trigger : une migration (`migrate.sh --new`), jamais `schema/`.
```

Documentation du modèle de données : [`docs/MCD-MLD.md`](../docs/MCD-MLD.md).

## Pour aller plus loin (hors scope projet scolaire)

En production, l'application PHP ne doit **jamais** se connecter avec `root`. Créer plutôt un utilisateur MariaDB dédié avec le strict minimum de droits (SELECT/INSERT/UPDATE/DELETE sur les tables métier, SELECT seul sur les référentiels). Exemple :

```sql
CREATE USER 'dayout_app'@'localhost' IDENTIFIED BY 'un_mot_de_passe_fort';
GRANT SELECT, INSERT, UPDATE, DELETE ON dayout.* TO 'dayout_app'@'localhost';
FLUSH PRIVILEGES;
```
