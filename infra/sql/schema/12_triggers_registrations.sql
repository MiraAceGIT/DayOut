-- Inscriptions : controles d'acces + maintien de events.registered_count.
-- "Active" = fn_registration_is_active(status) = status <> 'cancelled' (registered, attended, no_show).
-- registered_count = nombre d'inscriptions actives.

DELIMITER $$

-- Refuse l'inscription si user banni, score < 40, event non 'published' ou complet.
-- La ligne events est verrouillee (FOR UPDATE) : pas de course sur la derniere place.
DROP TRIGGER IF EXISTS trg_registrations_bi $$
CREATE TRIGGER trg_registrations_bi
BEFORE INSERT ON registrations
FOR EACH ROW
BEGIN
  CALL sp_assert_user_can_register(NEW.user_id);
  CALL sp_lock_event_seat(NEW.event_id, 1);
END $$

-- Une inscription devient active sur NEW.event_id alors qu'elle n'y etait pas comptee :
--   cancelled -> registered (reinscription) : memes controles que trg_registrations_bi ;
--   autre passage vers un statut actif ou changement d'event : capacite seule (correction admin).
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

-- Meme event : delta active(NEW) - active(OLD). Changement d'event : -active(OLD) sur l'ancien,
-- +active(NEW) sur le nouveau.
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

-- La suppression d'un user efface ses inscriptions par ON DELETE CASCADE, qui ne declenche pas
-- trg_registrations_ad : on decremente donc ici, avant la cascade.
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
