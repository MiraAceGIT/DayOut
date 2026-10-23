-- =============================================================================
-- DayOut — Triggers : événements (restriction école)
-- -----------------------------------------------------------------------------
-- NOTE MariaDB : utiliser `COLLATE utf8mb4_unicode_ci` dans les comparaisons
-- croisees schools.email_domain / NEW.email_domain pour eviter l'erreur
-- "Illegal mix of collations" quand connection_collation differe.
-- =============================================================================

DELIMITER $$

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

DELIMITER ;
