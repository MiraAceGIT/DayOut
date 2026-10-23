-- =============================================================================
-- DayOut — Triggers : contrôle du rôle admin sur les FK vers users
-- -----------------------------------------------------------------------------
-- NOTE MariaDB : utiliser `COLLATE utf8mb4_unicode_ci` dans les comparaisons
-- croisees schools.email_domain / NEW.email_domain pour eviter l'erreur
-- "Illegal mix of collations" quand connection_collation differe.
-- =============================================================================

DELIMITER $$

-- 9.10 Garantie "role admin" pour les colonnes FK pointant arbitrairement vers users.
--      Le CHECK ne peut pas interroger une autre table : triggers obligatoires.

-- 9.10.a report_actions.admin_id doit referencer un admin
DROP TRIGGER IF EXISTS trg_report_actions_bi $$
CREATE TRIGGER trg_report_actions_bi
BEFORE INSERT ON report_actions
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  SELECT role INTO v_role FROM users WHERE id = NEW.admin_id;
  IF v_role IS NULL OR v_role <> 'admin' THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'report_actions.admin_id must reference a user with role=admin';
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_report_actions_bu $$
CREATE TRIGGER trg_report_actions_bu
BEFORE UPDATE ON report_actions
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.admin_id <> OLD.admin_id THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.admin_id;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'report_actions.admin_id must reference a user with role=admin';
    END IF;
  END IF;
END $$

-- 9.10.b absence_corrections.reviewed_by doit etre admin (si renseigne)
DROP TRIGGER IF EXISTS trg_absence_corr_bi $$
CREATE TRIGGER trg_absence_corr_bi
BEFORE INSERT ON absence_corrections
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.reviewed_by IS NOT NULL THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.reviewed_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'absence_corrections.reviewed_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_absence_corr_bu $$
CREATE TRIGGER trg_absence_corr_bu
BEFORE UPDATE ON absence_corrections
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.reviewed_by IS NOT NULL
     AND (OLD.reviewed_by IS NULL OR NEW.reviewed_by <> OLD.reviewed_by) THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.reviewed_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'absence_corrections.reviewed_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

-- 9.10.c creator_profiles.reviewed_by doit etre admin (si renseigne)
DROP TRIGGER IF EXISTS trg_creator_profiles_bi $$
CREATE TRIGGER trg_creator_profiles_bi
BEFORE INSERT ON creator_profiles
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.reviewed_by IS NOT NULL THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.reviewed_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'creator_profiles.reviewed_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_creator_profiles_bu $$
CREATE TRIGGER trg_creator_profiles_bu
BEFORE UPDATE ON creator_profiles
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.reviewed_by IS NOT NULL
     AND (OLD.reviewed_by IS NULL OR NEW.reviewed_by <> OLD.reviewed_by) THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.reviewed_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'creator_profiles.reviewed_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

-- 9.10.d contact_messages.handled_by doit etre admin (si renseigne)
DROP TRIGGER IF EXISTS trg_contact_msg_bi $$
CREATE TRIGGER trg_contact_msg_bi
BEFORE INSERT ON contact_messages
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.handled_by IS NOT NULL THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.handled_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'contact_messages.handled_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_contact_msg_bu $$
CREATE TRIGGER trg_contact_msg_bu
BEFORE UPDATE ON contact_messages
FOR EACH ROW
BEGIN
  DECLARE v_role VARCHAR(16);
  IF NEW.handled_by IS NOT NULL
     AND (OLD.handled_by IS NULL OR NEW.handled_by <> OLD.handled_by) THEN
    SELECT role INTO v_role FROM users WHERE id = NEW.handled_by;
    IF v_role IS NULL OR v_role <> 'admin' THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'contact_messages.handled_by must reference a user with role=admin';
    END IF;
  END IF;
END $$

DELIMITER ;
