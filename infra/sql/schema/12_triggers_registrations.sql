-- =============================================================================
-- DayOut — Triggers : inscriptions (contrôles + registered_count)
-- -----------------------------------------------------------------------------
-- NOTE MariaDB : utiliser `COLLATE utf8mb4_unicode_ci` dans les comparaisons
-- croisees schools.email_domain / NEW.email_domain pour eviter l'erreur
-- "Illegal mix of collations" quand connection_collation differe.
-- =============================================================================

DELIMITER $$

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

-- 9.9 Blocage reinscription non controlee (cancelled -> registered via UPDATE)
--     Rejoue les memes checks que trg_registrations_bi pour empecher le contournement.
DROP TRIGGER IF EXISTS trg_registrations_bu $$
CREATE TRIGGER trg_registrations_bu
BEFORE UPDATE ON registrations
FOR EACH ROW
BEGIN
  DECLARE v_score   TINYINT UNSIGNED;
  DECLARE v_banned  TINYINT(1);
  DECLARE v_status  VARCHAR(16);
  DECLARE v_cap     INT UNSIGNED;
  DECLARE v_count   INT UNSIGNED;

  IF OLD.status = 'cancelled' AND NEW.status = 'registered' THEN
    SELECT reliability_score, banned INTO v_score, v_banned
    FROM users WHERE id = NEW.user_id;

    IF v_banned = 1 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User is banned';
    END IF;

    IF v_score < 40 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Reliability score too low (<40) to re-register';
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
  END IF;
END $$

DELIMITER ;
