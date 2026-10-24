-- DayOut — schéma pour MariaDB 10.4+ (XAMPP) / MySQL 8.0. La base `dayout` doit exister.
-- Fichiers schema/*.sql à jouer dans l'ordre (cf. infra/README.md).
-- Rejouable : FOREIGN_KEY_CHECKS=0 + DROP IF EXISTS, dans l'ordre inverse des dépendances.

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO';
SET FOREIGN_KEY_CHECKS = 0;

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

-- Routines de 09_routines.sql (les triggers partent avec leurs tables).
DROP PROCEDURE IF EXISTS sp_raise;
DROP FUNCTION  IF EXISTS fn_email_domain;
DROP FUNCTION  IF EXISTS fn_school_id_for_domain;
DROP PROCEDURE IF EXISTS sp_assert_domain_whitelisted;
DROP PROCEDURE IF EXISTS sp_assert_event_restriction;
DROP FUNCTION  IF EXISTS fn_registration_is_active;
DROP PROCEDURE IF EXISTS sp_assert_user_can_register;
DROP PROCEDURE IF EXISTS sp_lock_event_seat;
DROP PROCEDURE IF EXISTS sp_registered_count_add;
DROP FUNCTION  IF EXISTS fn_is_admin;
DROP PROCEDURE IF EXISTS sp_assert_admin;
DROP PROCEDURE IF EXISTS sp_assert_planning_item;
DROP PROCEDURE IF EXISTS sp_assert_report_target;

SET FOREIGN_KEY_CHECKS = 1;
