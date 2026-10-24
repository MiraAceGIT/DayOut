DELIMITER $$

-- Trigger car CHECK impossible sur une colonne FK (cf. 14_triggers_fk_checks.sql).
DROP TRIGGER IF EXISTS trg_events_bi $$
CREATE TRIGGER trg_events_bi
BEFORE INSERT ON events
FOR EACH ROW
BEGIN
  CALL sp_assert_event_restriction(NEW.school_restricted, NEW.restricted_school_id);
END $$

-- Revalide seulement si ces colonnes changent : un UPDATE de registered_count ne doit pas etre bloque.
DROP TRIGGER IF EXISTS trg_events_bu $$
CREATE TRIGGER trg_events_bu
BEFORE UPDATE ON events
FOR EACH ROW
BEGIN
  IF NOT (NEW.school_restricted <=> OLD.school_restricted
          AND NEW.restricted_school_id <=> OLD.restricted_school_id) THEN
    CALL sp_assert_event_restriction(NEW.school_restricted, NEW.restricted_school_id);
  END IF;
END $$

DELIMITER ;
