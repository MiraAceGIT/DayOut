-- Triggers car MariaDB refuse un CHECK sur une colonne FK ON DELETE SET NULL / ON UPDATE CASCADE (erreur 1901).
-- Les actions FK ne declenchent pas de trigger (id laisse a NULL) : en UPDATE, revalider seulement si ces colonnes changent.

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
