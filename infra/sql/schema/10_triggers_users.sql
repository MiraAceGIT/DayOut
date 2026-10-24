-- NOTE MariaDB : `COLLATE utf8mb4_unicode_ci` dans les comparaisons sur schools.email_domain
-- evite "Illegal mix of collations" quand la collation de connexion differe.

DELIMITER $$

-- Hors admin : le domaine de l'email doit etre une ecole active ; renseigne email_domain et school_id.
DROP TRIGGER IF EXISTS trg_users_bi_whitelist $$
CREATE TRIGGER trg_users_bi_whitelist
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
  DECLARE v_domain VARCHAR(120);
  DECLARE v_ok     INT DEFAULT 0;

  IF NEW.role <> 'admin' THEN
    SET v_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
    SET NEW.email_domain = v_domain;

    SELECT COUNT(*) INTO v_ok
    FROM schools
    WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
      AND active = 1;

    IF v_ok = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Email domain not in schools whitelist';
    END IF;

    IF NEW.school_id IS NULL THEN
      SET NEW.school_id = (
        SELECT id FROM schools
        WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
        LIMIT 1
      );
    END IF;
  ELSE
    SET NEW.email_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
  END IF;
END $$

-- Idem si l'email change (school_id n'est pas recalcule).
DROP TRIGGER IF EXISTS trg_users_bu_whitelist $$
CREATE TRIGGER trg_users_bu_whitelist
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
  DECLARE v_domain VARCHAR(120);
  DECLARE v_ok     INT DEFAULT 0;

  IF NEW.email <> OLD.email THEN
    IF NEW.role <> 'admin' THEN
      SET v_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
      SET NEW.email_domain = v_domain;

      SELECT COUNT(*) INTO v_ok
      FROM schools
      WHERE email_domain = v_domain COLLATE utf8mb4_unicode_ci
        AND active = 1;

      IF v_ok = 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'Email domain not in schools whitelist';
      END IF;
    ELSE
      SET NEW.email_domain = LOWER(SUBSTRING_INDEX(NEW.email, '@', -1));
    END IF;
  END IF;
END $$

DELIMITER ;
