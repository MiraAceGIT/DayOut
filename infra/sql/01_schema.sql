-- =============================================================================
-- DayOut — Schema (DDL)
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0 compatible
-- Engine: InnoDB   Charset: utf8mb4   Collation: utf8mb4_unicode_ci
-- NOTE: la base `dayout` doit être créée au préalable (phpMyAdmin).
-- Rejouable : FOREIGN_KEY_CHECKS=0 + DROP TABLE IF EXISTS en tête.
-- =============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Clean-up (ordre inverse des dépendances)
-- -----------------------------------------------------------------------------
DROP VIEW  IF EXISTS v_user_reliability;

DROP TABLE IF EXISTS contact_messages;
DROP TABLE IF EXISTS legal_pages;
DROP TABLE IF EXISTS faq_entries;
DROP TABLE IF EXISTS report_actions;
DROP TABLE IF EXISTS reports;
DROP TABLE IF EXISTS event_messages;
DROP TABLE IF EXISTS suggestion_dismissals;
DROP TABLE IF EXISTS planning_items;
DROP TABLE IF EXISTS plannings;
DROP TABLE IF EXISTS absence_corrections;
DROP TABLE IF EXISTS attendance_events;
DROP TABLE IF EXISTS paid_event_clicks;
DROP TABLE IF EXISTS registrations;
DROP TABLE IF EXISTS event_categories;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS user_interests;
DROP TABLE IF EXISTS creator_profiles;
DROP TABLE IF EXISTS email_verifications;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS schools;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. REFERENTIELS
-- =============================================================================

CREATE TABLE schools (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(160) NOT NULL,
  short_name    VARCHAR(32)  NULL,
  email_domain  VARCHAR(120) NOT NULL,
  city          VARCHAR(80)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_schools_domain (email_domain),
  KEY idx_schools_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(60)  NOT NULL,
  label      VARCHAR(80)  NOT NULL,
  icon       VARCHAR(40)  NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. UTILISATEURS
-- =============================================================================

CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email              VARCHAR(190) NOT NULL,
  email_domain       VARCHAR(120) NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  first_name         VARCHAR(80)  NOT NULL,
  last_name          VARCHAR(80)  NOT NULL,
  birth_date         DATE         NULL,
  phone              VARCHAR(32)  NULL,
  school_id          INT UNSIGNED NULL,
  role               ENUM('participant','creator','admin') NOT NULL DEFAULT 'participant',
  creator_status     ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  avatar_url         VARCHAR(255) NULL,
  bio                TEXT         NULL,
  email_verified     TINYINT(1)   NOT NULL DEFAULT 0,
  email_verified_at  DATETIME     NULL,
  reliability_score  TINYINT UNSIGNED NOT NULL DEFAULT 100,
  banned             TINYINT(1)   NOT NULL DEFAULT 0,
  banned_reason      VARCHAR(255) NULL,
  last_login_at      DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_school (school_id),
  KEY idx_users_role (role),
  KEY idx_users_domain (email_domain),
  CONSTRAINT fk_users_school
    FOREIGN KEY (school_id) REFERENCES schools(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_users_score CHECK (reliability_score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_email_verif_token (token_hash),
  KEY idx_email_verif_user (user_id),
  CONSTRAINT fk_email_verif_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE creator_profiles (
  user_id             BIGINT UNSIGNED NOT NULL,
  display_name        VARCHAR(120) NOT NULL,
  description         TEXT         NULL,
  website_url         VARCHAR(255) NULL,
  instagram_handle    VARCHAR(80)  NULL,
  submitted_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at         DATETIME     NULL,
  reviewed_by         BIGINT UNSIGNED NULL,
  review_notes        VARCHAR(255) NULL,
  PRIMARY KEY (user_id),
  KEY idx_creator_reviewer (reviewed_by),
  CONSTRAINT fk_creator_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_creator_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_interests (
  user_id      BIGINT UNSIGNED NOT NULL,
  category_id  INT    UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, category_id),
  KEY idx_ui_category (category_id),
  CONSTRAINT fk_ui_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ui_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. EVENEMENTS
-- =============================================================================

CREATE TABLE events (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_id           BIGINT UNSIGNED NOT NULL,
  title                VARCHAR(180) NOT NULL,
  slug                 VARCHAR(200) NOT NULL,
  description          TEXT         NOT NULL,
  cover_image_url      VARCHAR(255) NULL,
  start_at             DATETIME     NOT NULL,
  end_at               DATETIME     NOT NULL,
  venue_name           VARCHAR(160) NULL,
  address_line         VARCHAR(200) NULL,
  city                 VARCHAR(80)  NULL,
  postal_code          VARCHAR(20)  NULL,
  latitude             DECIMAL(9,6) NULL,
  longitude            DECIMAL(9,6) NULL,
  capacity             INT UNSIGNED NULL,
  registered_count     INT UNSIGNED NOT NULL DEFAULT 0,
  price_cents          INT UNSIGNED NOT NULL DEFAULT 0,
  currency             CHAR(3)      NOT NULL DEFAULT 'EUR',
  is_paid              TINYINT(1)   NOT NULL DEFAULT 0,
  external_ticket_url  VARCHAR(255) NULL,
  school_restricted    TINYINT(1)   NOT NULL DEFAULT 0,
  restricted_school_id INT UNSIGNED NULL,
  status               ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',
  published_at         DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_events_slug (slug),
  KEY idx_events_creator (creator_id),
  KEY idx_events_start (start_at),
  KEY idx_events_status (status),
  KEY idx_events_school (restricted_school_id),
  KEY idx_events_city (city),
  CONSTRAINT fk_events_creator
    FOREIGN KEY (creator_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_events_school
    FOREIGN KEY (restricted_school_id) REFERENCES schools(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_events_dates    CHECK (end_at > start_at),
  CONSTRAINT chk_events_price    CHECK ( (is_paid = 0 AND price_cents = 0) OR (is_paid = 1 AND price_cents >= 0) ),
  CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0),
  CONSTRAINT chk_events_regcount CHECK (capacity IS NULL OR registered_count <= capacity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_categories (
  event_id    BIGINT UNSIGNED NOT NULL,
  category_id INT    UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, category_id),
  KEY idx_ec_category (category_id),
  CONSTRAINT fk_ec_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ec_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. INSCRIPTIONS & FIABILITE
-- =============================================================================

CREATE TABLE registrations (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  status         ENUM('registered','cancelled','attended','no_show') NOT NULL DEFAULT 'registered',
  qr_code_hash   CHAR(64)     NOT NULL,
  registered_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at   DATETIME     NULL,
  checked_in_at  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_reg_event_user (event_id, user_id),
  UNIQUE KEY uk_reg_qr (qr_code_hash),
  KEY idx_reg_user (user_id),
  KEY idx_reg_status (status),
  CONSTRAINT fk_reg_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clics billetterie externe (compte 0.5 participation dans le score)
CREATE TABLE paid_event_clicks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  clicked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_click_event_user (event_id, user_id),
  KEY idx_click_user (user_id),
  CONSTRAINT fk_click_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_click_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal de participation (impact score fiabilite)
CREATE TABLE attendance_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  event_id    BIGINT UNSIGNED NOT NULL,
  kind        ENUM('attended','no_show','cancelled_late','paid_click','corrected') NOT NULL,
  score_delta SMALLINT     NOT NULL DEFAULT 0,
  weight      DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_user (user_id),
  KEY idx_att_event (event_id),
  KEY idx_att_kind (kind),
  CONSTRAINT fk_att_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_att_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE absence_corrections (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  reason          TEXT         NOT NULL,
  proof_url       VARCHAR(255) NULL,
  status          ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     BIGINT UNSIGNED NULL,
  reviewed_at     DATETIME     NULL,
  review_notes    VARCHAR(255) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_absence_registration (registration_id),
  KEY idx_absence_user (user_id),
  KEY idx_absence_reviewer (reviewed_by),
  CONSTRAINT fk_absence_registration
    FOREIGN KEY (registration_id) REFERENCES registrations(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absence_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absence_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. PLANNING PERSONNEL & SUGGESTIONS
-- =============================================================================

CREATE TABLE plannings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL DEFAULT 'Mon planning',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plannings_user (user_id),
  CONSTRAINT fk_plannings_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planning_items (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  planning_id BIGINT UNSIGNED NOT NULL,
  event_id    BIGINT UNSIGNED NULL,
  title       VARCHAR(180) NULL,
  start_at    DATETIME     NOT NULL,
  end_at      DATETIME     NOT NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_item_planning_event (planning_id, event_id),
  KEY idx_plan_item_planning (planning_id),
  KEY idx_plan_item_event (event_id),
  CONSTRAINT fk_plan_item_planning
    FOREIGN KEY (planning_id) REFERENCES plannings(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_plan_item_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_plan_item_dates CHECK (end_at > start_at),
  CONSTRAINT chk_plan_item_source CHECK (event_id IS NOT NULL OR title IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suggestion_dismissals (
  user_id       BIGINT UNSIGNED NOT NULL,
  event_id      BIGINT UNSIGNED NOT NULL,
  dismissed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, event_id),
  KEY idx_sd_event (event_id),
  CONSTRAINT fk_sd_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sd_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. INTERACTIONS SOCIALES & MODERATION
-- =============================================================================

CREATE TABLE event_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  edited_at   DATETIME     NULL,
  deleted_at  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_em_event (event_id),
  KEY idx_em_user (user_id),
  CONSTRAINT fk_em_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_em_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id    BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('event','user','message') NOT NULL,
  target_event_id    BIGINT UNSIGNED NULL,
  target_user_id     BIGINT UNSIGNED NULL,
  target_message_id  BIGINT UNSIGNED NULL,
  reason         VARCHAR(80) NOT NULL,
  details        TEXT        NULL,
  status         ENUM('open','in_review','resolved','rejected') NOT NULL DEFAULT 'open',
  created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_reporter (reporter_id),
  KEY idx_reports_target_event (target_event_id),
  KEY idx_reports_target_user  (target_user_id),
  KEY idx_reports_target_msg   (target_message_id),
  KEY idx_reports_status (status),
  CONSTRAINT fk_reports_reporter
    FOREIGN KEY (reporter_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reports_event
    FOREIGN KEY (target_event_id) REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reports_user
    FOREIGN KEY (target_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reports_message
    FOREIGN KEY (target_message_id) REFERENCES event_messages(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_report_target CHECK (
    (target_type = 'event'   AND target_event_id   IS NOT NULL AND target_user_id IS NULL AND target_message_id IS NULL) OR
    (target_type = 'user'    AND target_user_id    IS NOT NULL AND target_event_id IS NULL AND target_message_id IS NULL) OR
    (target_type = 'message' AND target_message_id IS NOT NULL AND target_event_id IS NULL AND target_user_id    IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_actions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id  BIGINT UNSIGNED NOT NULL,
  admin_id   BIGINT UNSIGNED NOT NULL,
  action     ENUM('warn','hide_message','unpublish_event','ban_user','dismiss','note') NOT NULL,
  comment    VARCHAR(500) NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ra_report (report_id),
  KEY idx_ra_admin (admin_id),
  CONSTRAINT fk_ra_report
    FOREIGN KEY (report_id) REFERENCES reports(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ra_admin
    FOREIGN KEY (admin_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. CONTENU STATIQUE / SUPPORT
-- =============================================================================

CREATE TABLE faq_entries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question    VARCHAR(255) NOT NULL,
  answer      TEXT         NOT NULL,
  sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faq_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legal_pages (
  slug        VARCHAR(60)  NOT NULL,
  title       VARCHAR(160) NOT NULL,
  body_md     MEDIUMTEXT   NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  email       VARCHAR(190) NOT NULL,
  subject     VARCHAR(160) NOT NULL,
  body        TEXT         NOT NULL,
  handled     TINYINT(1)   NOT NULL DEFAULT 0,
  handled_at  DATETIME     NULL,
  handled_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_user (user_id),
  KEY idx_contact_handler (handled_by),
  KEY idx_contact_handled (handled),
  CONSTRAINT fk_contact_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_contact_handler
    FOREIGN KEY (handled_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. VUE : SCORE DE FIABILITE (colonne + journal)
-- -----------------------------------------------------------------------------
-- Approche hybride :
--  - users.reliability_score = valeur courante denormalisee (0..100)
--  - attendance_events      = journal detaille (audit, debuggable, rejouable)
--  - v_user_reliability     = agregation en temps reel pour analyse admin
-- =============================================================================

CREATE OR REPLACE VIEW v_user_reliability AS
SELECT
  u.id                                                           AS user_id,
  u.email,
  CONCAT(u.first_name, ' ', u.last_name)                         AS full_name,
  u.reliability_score                                            AS current_score,
  COALESCE(SUM(CASE WHEN ae.kind = 'attended'       THEN 1 ELSE 0 END), 0) AS nb_attended,
  COALESCE(SUM(CASE WHEN ae.kind = 'no_show'        THEN 1 ELSE 0 END), 0) AS nb_no_show,
  COALESCE(SUM(CASE WHEN ae.kind = 'cancelled_late' THEN 1 ELSE 0 END), 0) AS nb_cancelled_late,
  COALESCE(SUM(CASE WHEN ae.kind = 'paid_click'     THEN 1 ELSE 0 END), 0) AS nb_paid_clicks,
  COALESCE(SUM(CASE WHEN ae.kind = 'corrected'      THEN 1 ELSE 0 END), 0) AS nb_corrections,
  COALESCE(SUM(ae.score_delta), 0)                               AS cumulative_delta,
  COALESCE(SUM(
    CASE
      WHEN ae.kind = 'attended'   THEN 1.0 * ae.weight
      WHEN ae.kind = 'paid_click' THEN 0.5 * ae.weight
      ELSE 0
    END
  ), 0)                                                          AS participation_count
FROM users u
LEFT JOIN attendance_events ae ON ae.user_id = u.id
GROUP BY u.id, u.email, u.first_name, u.last_name, u.reliability_score;

-- =============================================================================
-- 9. TRIGGERS METIER
-- -----------------------------------------------------------------------------
-- NOTE MariaDB : utiliser `COLLATE utf8mb4_unicode_ci` dans les comparaisons
-- croisees schools.email_domain / NEW.email_domain pour eviter l'erreur
-- "Illegal mix of collations" quand connection_collation differe.
-- =============================================================================

DELIMITER $$

-- 9.1 Whitelist ecoles sur inscription (INSERT)
DROP TRIGGER IF EXISTS trg_users_bi_whitelist $$
CREATE TRIGGER trg_users_bi_whitelist
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_domain VARCHAR(120);
  DECLARE v_ok     INT DEFAULT 0;

  IF NEW.role <> 'admin' THEN
    SET v_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
    SET NEW.email_domain = v_domain;

    SELECT COUNT(*) INTO v_ok
    FROM schools
    WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
      AND active = 1;

    IF v_ok = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Email domain not in schools whitelist';
    END IF;

    IF NEW.school_id IS NULL THEN
      SET NEW.school_id = (
        SELECT id FROM schools
        WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
        LIMIT 1
      );
    END IF;
  ELSE
    SET NEW.email_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
  END IF;
END $$

-- 9.2 Whitelist ecoles si l'email change (UPDATE)
DROP TRIGGER IF EXISTS trg_users_bu_whitelist $$
CREATE TRIGGER trg_users_bu_whitelist
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE v_domain VARCHAR(120);
  DECLARE v_ok     INT DEFAULT 0;

  IF NEW.email <> OLD.email THEN
    IF NEW.role <> 'admin' THEN
      SET v_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
      SET NEW.email_domain = v_domain;

      SELECT COUNT(*) INTO v_ok
      FROM schools
      WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
        AND active = 1;

      IF v_ok = 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Email domain not in schools whitelist';
      END IF;
    ELSE
      SET NEW.email_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
    END IF;
  END IF;
END $$

-- 9.3 Coherence school_restricted <=> restricted_school_id (BEFORE INSERT)
-- (CHECK impossible directement sur une colonne FK -> on utilise un trigger)
DROP TRIGGER IF EXISTS trg_events_bi $$
CREATE TRIGGER trg_events_bi
BEFORE INSERT ON events
FOR EACH ROW
BEGIN
  IF NEW.school_restricted = 1 AND NEW.restricted_school_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'school_restricted=1 requires restricted_school_id';
  END IF;
  IF NEW.school_restricted = 0 AND NEW.restricted_school_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'school_restricted=0 forbids restricted_school_id';
  END IF;
END $$

-- 9.4 Idem en UPDATE
DROP TRIGGER IF EXISTS trg_events_bu $$
CREATE TRIGGER trg_events_bu
BEFORE UPDATE ON events
FOR EACH ROW
BEGIN
  IF NEW.school_restricted = 1 AND NEW.restricted_school_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'school_restricted=1 requires restricted_school_id';
  END IF;
  IF NEW.school_restricted = 0 AND NEW.restricted_school_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'school_restricted=0 forbids restricted_school_id';
  END IF;
END $$

-- 9.5 Blocage inscription si score < 40, si banni, ou si event non-published
--     + maintenance de events.registered_count (+1 a l'inscription)
DROP TRIGGER IF EXISTS trg_registrations_bi $$
CREATE TRIGGER trg_registrations_bi
BEFORE INSERT ON registrations
FOR EACH ROW
BEGIN
  DECLARE v_score   TINYINT UNSIGNED;
  DECLARE v_banned  TINYINT(1);
  DECLARE v_status  VARCHAR(16);
  DECLARE v_cap     INT UNSIGNED;
  DECLARE v_count   INT UNSIGNED;

  SELECT reliability_score, banned INTO v_score, v_banned
  FROM users WHERE id = NEW.user_id;

  IF v_banned = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User is banned';
  END IF;

  IF v_score < 40 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Reliability score too low (<40) to register';
  END IF;

  SELECT status, capacity, registered_count
    INTO v_status, v_cap, v_count
  FROM events WHERE id = NEW.event_id;

  IF v_status <> 'published' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Event is not open for registration';
  END IF;

  IF v_cap IS NOT NULL AND v_count >= v_cap THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Event is full';
  END IF;
END $$

-- 9.6 Apres insertion : +1 sur registered_count si status 'registered'
DROP TRIGGER IF EXISTS trg_registrations_ai $$
CREATE TRIGGER trg_registrations_ai
AFTER INSERT ON registrations
FOR EACH ROW
BEGIN
  IF NEW.status = 'registered' THEN
    UPDATE events SET registered_count = registered_count + 1
    WHERE id = NEW.event_id;
  END IF;
END $$

-- 9.7 Maintenance registered_count sur changement de status
DROP TRIGGER IF EXISTS trg_registrations_au $$
CREATE TRIGGER trg_registrations_au
AFTER UPDATE ON registrations
FOR EACH ROW
BEGIN
  -- Transition : vers 'cancelled' -> decrement
  IF OLD.status IN ('registered','attended') AND NEW.status = 'cancelled' THEN
    UPDATE events SET registered_count = GREATEST(registered_count - 1, 0)
    WHERE id = NEW.event_id;
  END IF;
  -- Reinscription (cancelled -> registered) -> increment
  IF OLD.status = 'cancelled' AND NEW.status = 'registered' THEN
    UPDATE events SET registered_count = registered_count + 1
    WHERE id = NEW.event_id;
  END IF;
END $$

-- 9.8 Decrement a la suppression d'une inscription active
DROP TRIGGER IF EXISTS trg_registrations_ad $$
CREATE TRIGGER trg_registrations_ad
AFTER DELETE ON registrations
FOR EACH ROW
BEGIN
  IF OLD.status IN ('registered','attended') THEN
    UPDATE events SET registered_count = GREATEST(registered_count - 1, 0)
    WHERE id = OLD.event_id;
  END IF;
END $$

DELIMITER ;

-- =============================================================================
-- Fin de 01_schema.sql
-- =============================================================================
