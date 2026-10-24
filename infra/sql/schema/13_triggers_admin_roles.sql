DELIMITER $$

-- Les FK vers users reservees aux admins sont verifiees par trigger (un CHECK ne peut pas lire une autre table).
-- Regle unique : sp_assert_admin (09_routines.sql), qui ignore une valeur NULL. En UPDATE, on ne
-- revalide que si la colonne change.

DROP TRIGGER IF EXISTS trg_report_actions_bi $$
CREATE TRIGGER trg_report_actions_bi
BEFORE INSERT ON report_actions
FOR EACH ROW
BEGIN
  CALL sp_assert_admin(NEW.admin_id, 50401, 'report_actions.admin_id must reference a user with role=admin');
END $$

DROP TRIGGER IF EXISTS trg_report_actions_bu $$
CREATE TRIGGER trg_report_actions_bu
BEFORE UPDATE ON report_actions
FOR EACH ROW
BEGIN
  IF NOT (NEW.admin_id <=> OLD.admin_id) THEN
    CALL sp_assert_admin(NEW.admin_id, 50401, 'report_actions.admin_id must reference a user with role=admin');
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_absence_corr_bi $$
CREATE TRIGGER trg_absence_corr_bi
BEFORE INSERT ON absence_corrections
FOR EACH ROW
BEGIN
  CALL sp_assert_admin(NEW.reviewed_by, 50402, 'absence_corrections.reviewed_by must reference a user with role=admin');
END $$

DROP TRIGGER IF EXISTS trg_absence_corr_bu $$
CREATE TRIGGER trg_absence_corr_bu
BEFORE UPDATE ON absence_corrections
FOR EACH ROW
BEGIN
  IF NOT (NEW.reviewed_by <=> OLD.reviewed_by) THEN
    CALL sp_assert_admin(NEW.reviewed_by, 50402, 'absence_corrections.reviewed_by must reference a user with role=admin');
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_creator_profiles_bi $$
CREATE TRIGGER trg_creator_profiles_bi
BEFORE INSERT ON creator_profiles
FOR EACH ROW
BEGIN
  CALL sp_assert_admin(NEW.reviewed_by, 50403, 'creator_profiles.reviewed_by must reference a user with role=admin');
END $$

DROP TRIGGER IF EXISTS trg_creator_profiles_bu $$
CREATE TRIGGER trg_creator_profiles_bu
BEFORE UPDATE ON creator_profiles
FOR EACH ROW
BEGIN
  IF NOT (NEW.reviewed_by <=> OLD.reviewed_by) THEN
    CALL sp_assert_admin(NEW.reviewed_by, 50403, 'creator_profiles.reviewed_by must reference a user with role=admin');
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_contact_msg_bi $$
CREATE TRIGGER trg_contact_msg_bi
BEFORE INSERT ON contact_messages
FOR EACH ROW
BEGIN
  CALL sp_assert_admin(NEW.handled_by, 50404, 'contact_messages.handled_by must reference a user with role=admin');
END $$

DROP TRIGGER IF EXISTS trg_contact_msg_bu $$
CREATE TRIGGER trg_contact_msg_bu
BEFORE UPDATE ON contact_messages
FOR EACH ROW
BEGIN
  IF NOT (NEW.handled_by <=> OLD.handled_by) THEN
    CALL sp_assert_admin(NEW.handled_by, 50404, 'contact_messages.handled_by must reference a user with role=admin');
  END IF;
END $$

DELIMITER ;
