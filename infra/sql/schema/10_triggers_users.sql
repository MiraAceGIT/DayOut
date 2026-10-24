DELIMITER $$

DROP TRIGGER IF EXISTS trg_users_bi_whitelist $$
CREATE TRIGGER trg_users_bi_whitelist
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  SET NEW.email_domain = fn_email_domain(NEW.email);
  IF NEW.role <> 'admin' THEN
    CALL sp_assert_domain_whitelisted(NEW.email_domain);
    IF NEW.school_id IS NULL THEN
      SET NEW.school_id = fn_school_id_for_domain(NEW.email_domain);
    END IF;
  END IF;
END $$

-- Le role compte aussi : un admin hors whitelist retrograde doit etre revalide.
DROP TRIGGER IF EXISTS trg_users_bu_whitelist $$
CREATE TRIGGER trg_users_bu_whitelist
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  IF NOT (NEW.email <=> OLD.email AND NEW.role <=> OLD.role) THEN
    SET NEW.email_domain = fn_email_domain(NEW.email);
    IF NEW.role <> 'admin' THEN
      CALL sp_assert_domain_whitelisted(NEW.email_domain);
    END IF;
    IF NOT (NEW.email_domain <=> OLD.email_domain) THEN
      SET NEW.school_id = fn_school_id_for_domain(NEW.email_domain);
    END IF;
  END IF;
END $$

DELIMITER ;
