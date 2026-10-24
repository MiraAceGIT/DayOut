DELIMITER $$

-- Refuse l'inscription si user banni, score < 40, event non 'published' ou complet.
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

-- registered_count : -1 vers 'cancelled', +1 sur reinscription (cancelled -> registered).
DROP TRIGGER IF EXISTS trg_registrations_au $$
CREATE TRIGGER trg_registrations_au
AFTER UPDATE ON registrations
FOR EACH ROW
BEGIN
  IF OLD.status IN ('registered','attended') AND NEW.status = 'cancelled' THEN
    UPDATE events SET registered_count = GREATEST(registered_count - 1, 0)
    WHERE id = NEW.event_id;
  END IF;
  IF OLD.status = 'cancelled' AND NEW.status = 'registered' THEN
    UPDATE events SET registered_count = registered_count + 1
    WHERE id = NEW.event_id;
  END IF;
END $$

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

-- Reinscription (cancelled -> registered) : rejoue les controles de trg_registrations_bi.
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
