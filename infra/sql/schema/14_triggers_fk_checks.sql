-- Contraintes qui seraient des CHECK, mais MariaDB interdit un CHECK sur une colonne
-- FK avec ON DELETE SET NULL / ON UPDATE CASCADE.
-- Les actions FK ne declenchent pas de trigger : apres suppression de la cible, la ligne
-- garde un id NULL. On ne revalide donc en UPDATE que si les colonnes concernees changent.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_planning_items_bi $$
CREATE TRIGGER trg_planning_items_bi
BEFORE INSERT ON planning_items
FOR EACH ROW
BEGIN
  IF NEW.event_id IS NULL AND NEW.title IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'planning_items requires event_id or title';
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_planning_items_bu $$
CREATE TRIGGER trg_planning_items_bu
BEFORE UPDATE ON planning_items
FOR EACH ROW
BEGIN
  IF NOT (NEW.event_id <=> OLD.event_id AND NEW.title <=> OLD.title)
     AND NEW.event_id IS NULL AND NEW.title IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'planning_items requires event_id or title';
  END IF;
END $$

-- Exactement une cible renseignee, celle correspondant a target_type.
DROP TRIGGER IF EXISTS trg_reports_bi $$
CREATE TRIGGER trg_reports_bi
BEFORE INSERT ON reports
FOR EACH ROW
BEGIN
  IF NOT (
    (NEW.target_type = 'event'   AND NEW.target_event_id   IS NOT NULL AND NEW.target_user_id  IS NULL AND NEW.target_message_id IS NULL) OR
    (NEW.target_type = 'user'    AND NEW.target_user_id    IS NOT NULL AND NEW.target_event_id IS NULL AND NEW.target_message_id IS NULL) OR
    (NEW.target_type = 'message' AND NEW.target_message_id IS NOT NULL AND NEW.target_event_id IS NULL AND NEW.target_user_id    IS NULL)
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'reports target_*_id does not match target_type';
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_reports_bu $$
CREATE TRIGGER trg_reports_bu
BEFORE UPDATE ON reports
FOR EACH ROW
BEGIN
  IF NOT (NEW.target_type <=> OLD.target_type
          AND NEW.target_event_id <=> OLD.target_event_id
          AND NEW.target_user_id <=> OLD.target_user_id
          AND NEW.target_message_id <=> OLD.target_message_id)
     AND NOT (
    (NEW.target_type = 'event'   AND NEW.target_event_id   IS NOT NULL AND NEW.target_user_id  IS NULL AND NEW.target_message_id IS NULL) OR
    (NEW.target_type = 'user'    AND NEW.target_user_id    IS NOT NULL AND NEW.target_event_id IS NULL AND NEW.target_message_id IS NULL) OR
    (NEW.target_type = 'message' AND NEW.target_message_id IS NOT NULL AND NEW.target_event_id IS NULL AND NEW.target_user_id    IS NULL)
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'reports target_*_id does not match target_type';
  END IF;
END $$

DELIMITER ;
