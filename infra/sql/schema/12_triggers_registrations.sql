DELIMITER $$

-- sp_lock_event_seat verrouille la ligne events (FOR UPDATE) : pas de course sur la derniere place.
DROP TRIGGER IF EXISTS trg_registrations_bi $$
CREATE TRIGGER trg_registrations_bi
BEFORE INSERT ON registrations
FOR EACH ROW
BEGIN
  CALL sp_assert_user_can_register(NEW.user_id);
  CALL sp_lock_event_seat(NEW.event_id, 1);
END $$

-- Devient active sur NEW.event_id : reinscription (cancelled -> registered) = controles complets,
-- autre cas (correction admin, changement d'event) = capacite seule.
DROP TRIGGER IF EXISTS trg_registrations_bu $$
CREATE TRIGGER trg_registrations_bu
BEFORE UPDATE ON registrations
FOR EACH ROW
BEGIN
  IF fn_registration_is_active(NEW.status)
     AND NOT (fn_registration_is_active(OLD.status) AND NEW.event_id = OLD.event_id) THEN
    IF OLD.status = 'cancelled' AND NEW.status = 'registered' THEN
      CALL sp_assert_user_can_register(NEW.user_id);
      CALL sp_lock_event_seat(NEW.event_id, 1);
    ELSE
      CALL sp_lock_event_seat(NEW.event_id, 0);
    END IF;
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_registrations_ai $$
CREATE TRIGGER trg_registrations_ai
AFTER INSERT ON registrations
FOR EACH ROW
BEGIN
  CALL sp_registered_count_add(NEW.event_id, fn_registration_is_active(NEW.status));
END $$

DROP TRIGGER IF EXISTS trg_registrations_au $$
CREATE TRIGGER trg_registrations_au
AFTER UPDATE ON registrations
FOR EACH ROW
BEGIN
  IF NEW.event_id = OLD.event_id THEN
    CALL sp_registered_count_add(NEW.event_id,
         fn_registration_is_active(NEW.status) - fn_registration_is_active(OLD.status));
  ELSE
    CALL sp_registered_count_add(OLD.event_id, -fn_registration_is_active(OLD.status));
    CALL sp_registered_count_add(NEW.event_id,  fn_registration_is_active(NEW.status));
  END IF;
END $$

DROP TRIGGER IF EXISTS trg_registrations_ad $$
CREATE TRIGGER trg_registrations_ad
AFTER DELETE ON registrations
FOR EACH ROW
BEGIN
  CALL sp_registered_count_add(OLD.event_id, -fn_registration_is_active(OLD.status));
END $$

-- ON DELETE CASCADE ne declenche pas trg_registrations_ad : on decremente ici, avant la cascade.
DROP TRIGGER IF EXISTS trg_users_bd_registrations $$
CREATE TRIGGER trg_users_bd_registrations
BEFORE DELETE ON users
FOR EACH ROW
BEGIN
  UPDATE events e
  JOIN registrations r ON r.event_id = e.id
  SET e.registered_count = e.registered_count - 1
  WHERE r.user_id = OLD.id
    AND fn_registration_is_active(r.status) = 1;
END $$

DELIMITER ;
