-- MYSQL_ERRNO (table dans infra/README.md) : 501xx users, 502xx events, 503xx registrations,
-- 504xx roles admin, 505xx planning/moderation. Toute nouvelle routine : son DROP dans 00_reset.sql.

DELIMITER $$

-- MYSQL_ERRNO accepte une variable depuis MariaDB 10.4 ; ailleurs, codes en litteraux (grep).
DROP PROCEDURE IF EXISTS sp_raise $$
CREATE PROCEDURE sp_raise(IN p_errno SMALLINT UNSIGNED, IN p_message VARCHAR(128))
  DETERMINISTIC NO SQL
BEGIN
  SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = p_errno, MESSAGE_TEXT = p_message;
END $$

DROP FUNCTION IF EXISTS fn_email_domain $$
CREATE FUNCTION fn_email_domain(p_email VARCHAR(190)) RETURNS VARCHAR(120)
  DETERMINISTIC NO SQL
BEGIN
  RETURN LOWER(SUBSTRING_INDEX(p_email, '@', -1));
END $$

-- Ecole active ou non. COLLATE : evite "Illegal mix of collations" si la connexion differe.
DROP FUNCTION IF EXISTS fn_school_id_for_domain $$
CREATE FUNCTION fn_school_id_for_domain(p_domain VARCHAR(120)) RETURNS INT UNSIGNED
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  RETURN (SELECT id FROM schools
          WHERE email_domain = p_domain COLLATE utf8mb4_unicode_ci
          LIMIT 1);
END $$

DROP PROCEDURE IF EXISTS sp_assert_domain_whitelisted $$
CREATE PROCEDURE sp_assert_domain_whitelisted(IN p_domain VARCHAR(120))
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  IF NOT EXISTS (SELECT 1 FROM schools
                 WHERE email_domain = p_domain COLLATE utf8mb4_unicode_ci
                   AND active = 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50101, MESSAGE_TEXT = 'Email domain not in schools whitelist';
  END IF;
END $$

DROP PROCEDURE IF EXISTS sp_assert_event_restriction $$
CREATE PROCEDURE sp_assert_event_restriction(IN p_restricted TINYINT, IN p_school_id INT UNSIGNED)
  DETERMINISTIC NO SQL
BEGIN
  IF p_restricted = 1 AND p_school_id IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50201, MESSAGE_TEXT = 'school_restricted=1 requires restricted_school_id';
  END IF;
  IF p_restricted = 0 AND p_school_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50202, MESSAGE_TEXT = 'school_restricted=0 forbids restricted_school_id';
  END IF;
END $$

-- Seule definition d'une inscription active (comptee dans registered_count) : tout sauf 'cancelled'.
DROP FUNCTION IF EXISTS fn_registration_is_active $$
CREATE FUNCTION fn_registration_is_active(p_status VARCHAR(16)) RETURNS TINYINT
  DETERMINISTIC NO SQL
BEGIN
  RETURN IF(p_status IS NOT NULL AND p_status <> 'cancelled', 1, 0);
END $$

DROP PROCEDURE IF EXISTS sp_assert_user_can_register $$
CREATE PROCEDURE sp_assert_user_can_register(IN p_user_id BIGINT UNSIGNED)
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE v_score  TINYINT UNSIGNED;
  DECLARE v_banned TINYINT(1);

  SELECT reliability_score, banned INTO v_score, v_banned
  FROM users WHERE id = p_user_id;

  IF v_banned = 1 THEN
    SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50301, MESSAGE_TEXT = 'User is banned';
  END IF;
  IF v_score < 40 THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50302, MESSAGE_TEXT = 'Reliability score too low (<40) to register';
  END IF;
END $$

-- FOR UPDATE : verrou garde jusqu'a la fin de la transaction, la 2e inscription concurrente
-- voit le registered_count a jour. p_require_open = 0 : capacite seule (correction admin).
DROP PROCEDURE IF EXISTS sp_lock_event_seat $$
CREATE PROCEDURE sp_lock_event_seat(IN p_event_id BIGINT UNSIGNED, IN p_require_open TINYINT)
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  DECLARE v_status VARCHAR(16);
  DECLARE v_cap    INT UNSIGNED;
  DECLARE v_count  INT UNSIGNED;

  SELECT status, capacity, registered_count
    INTO v_status, v_cap, v_count
  FROM events WHERE id = p_event_id
  FOR UPDATE;

  IF p_require_open = 1 AND v_status <> 'published' THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50303, MESSAGE_TEXT = 'Event is not open for registration';
  END IF;
  IF v_cap IS NOT NULL AND v_count >= v_cap THEN
    SIGNAL SQLSTATE '45000' SET MYSQL_ERRNO = 50304, MESSAGE_TEXT = 'Event is full';
  END IF;
END $$

-- Pas de GREATEST(...,0) : un passage sous 0 doit lever "out of range" (UNSIGNED), pas etre masque.
DROP PROCEDURE IF EXISTS sp_registered_count_add $$
CREATE PROCEDURE sp_registered_count_add(IN p_event_id BIGINT UNSIGNED, IN p_delta INT)
  NOT DETERMINISTIC MODIFIES SQL DATA
BEGIN
  IF p_delta > 0 THEN
    UPDATE events SET registered_count = registered_count + p_delta WHERE id = p_event_id;
  ELSEIF p_delta < 0 THEN
    UPDATE events SET registered_count = registered_count - (-p_delta) WHERE id = p_event_id;
  END IF;
END $$

DROP FUNCTION IF EXISTS fn_is_admin $$
CREATE FUNCTION fn_is_admin(p_user_id BIGINT UNSIGNED) RETURNS TINYINT
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  RETURN EXISTS (SELECT 1 FROM users WHERE id = p_user_id AND role = 'admin');
END $$

-- p_user_id NULL accepte (colonnes nullables).
DROP PROCEDURE IF EXISTS sp_assert_admin $$
CREATE PROCEDURE sp_assert_admin(IN p_user_id BIGINT UNSIGNED,
                                 IN p_errno SMALLINT UNSIGNED, IN p_message VARCHAR(128))
  NOT DETERMINISTIC READS SQL DATA
BEGIN
  IF p_user_id IS NOT NULL AND NOT fn_is_admin(p_user_id) THEN
    CALL sp_raise(p_errno, p_message);
  END IF;
END $$

DROP PROCEDURE IF EXISTS sp_assert_planning_item $$
CREATE PROCEDURE sp_assert_planning_item(IN p_event_id BIGINT UNSIGNED, IN p_title VARCHAR(180))
  DETERMINISTIC NO SQL
BEGIN
  IF p_event_id IS NULL AND p_title IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50501, MESSAGE_TEXT = 'planning_items requires event_id or title';
  END IF;
END $$

-- Exactement une cible renseignee, celle correspondant a target_type.
DROP PROCEDURE IF EXISTS sp_assert_report_target $$
CREATE PROCEDURE sp_assert_report_target(IN p_type VARCHAR(16), IN p_event_id BIGINT UNSIGNED,
                                         IN p_user_id BIGINT UNSIGNED, IN p_message_id BIGINT UNSIGNED)
  DETERMINISTIC NO SQL
BEGIN
  IF NOT (
    (p_type = 'event'   AND p_event_id   IS NOT NULL AND p_user_id  IS NULL AND p_message_id IS NULL) OR
    (p_type = 'user'    AND p_user_id    IS NOT NULL AND p_event_id IS NULL AND p_message_id IS NULL) OR
    (p_type = 'message' AND p_message_id IS NOT NULL AND p_event_id IS NULL AND p_user_id    IS NULL)
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MYSQL_ERRNO = 50502, MESSAGE_TEXT = 'reports target_*_id does not match target_type';
  END IF;
END $$

DELIMITER ;
