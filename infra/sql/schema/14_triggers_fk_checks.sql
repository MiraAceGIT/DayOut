-- Contraintes qui seraient des CHECK, mais MariaDB interdit un CHECK sur une colonne
-- FK avec ON DELETE SET NULL / ON UPDATE CASCADE.
-- Les actions FK ne declenchent pas de trigger : apres suppression de la cible, la ligne
-- garde un id NULL. On ne revalide donc en UPDATE que si les colonnes concernees changent.
-- Regles : sp_assert_planning_item / sp_assert_report_target (09_routines.sql).

DELIMITER $$

DROP TRIGGER IF EXISTS trg_planning_items_bi $$
CREATE TRIGGER trg_planning_items_bi
BEFORE INSERT ON planning_items
FOR EACH ROW
BEGIN
  CALL sp_assert_planning_item(NEW.event_id, NEW.title);
END $$

DROP TRIGGER IF EXISTS trg_planning_items_bu $$
CREATE TRIGGER trg_planning_items_bu
BEFORE UPDATE ON planning_items
FOR EACH ROW
BEGIN
  IF NOT (NEW.event_id <=> OLD.event_id AND NEW.title <=> OLD.title) THEN
    CALL sp_assert_planning_item(NEW.event_id, NEW.title);
  END IF;
END $$

-- Exactement une cible renseignee, celle correspondant a target_type.
DROP TRIGGER IF EXISTS trg_reports_bi $$
CREATE TRIGGER trg_reports_bi
BEFORE INSERT ON reports
FOR EACH ROW
BEGIN
  CALL sp_assert_report_target(NEW.target_type, NEW.target_event_id,
                               NEW.target_user_id, NEW.target_message_id);
END $$

DROP TRIGGER IF EXISTS trg_reports_bu $$
CREATE TRIGGER trg_reports_bu
BEFORE UPDATE ON reports
FOR EACH ROW
BEGIN
  IF NOT (NEW.target_type <=> OLD.target_type
          AND NEW.target_event_id <=> OLD.target_event_id
          AND NEW.target_user_id <=> OLD.target_user_id
          AND NEW.target_message_id <=> OLD.target_message_id) THEN
    CALL sp_assert_report_target(NEW.target_type, NEW.target_event_id,
                                 NEW.target_user_id, NEW.target_message_id);
  END IF;
END $$

DELIMITER ;
