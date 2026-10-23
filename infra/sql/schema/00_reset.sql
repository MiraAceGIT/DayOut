-- =============================================================================
-- DayOut — Schema (DDL)
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0 compatible
-- Engine: InnoDB   Charset: utf8mb4   Collation: utf8mb4_unicode_ci
-- NOTE: la base `dayout` doit être créée au préalable (phpMyAdmin).
-- Rejouable : FOREIGN_KEY_CHECKS=0 + DROP TABLE IF EXISTS en tête.
-- Fichiers à jouer dans l'ordre (cf. infra/README.md).
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
