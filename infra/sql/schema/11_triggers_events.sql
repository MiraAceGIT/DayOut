DELIMITER $$

-- Coherence school_restricted <=> restricted_school_id (trigger car CHECK impossible sur une colonne FK).
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

DELIMITER ;
