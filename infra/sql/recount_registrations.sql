-- Recalcule events.registered_count depuis registrations (inscriptions actives uniquement,
-- meme definition que les triggers : fn_registration_is_active = status <> 'cancelled').
-- A jouer apres un import ou une modification hors triggers (ex. desactivation des triggers,
-- DELETE en cascade non couvert). Rejouable, sans effet si les compteurs sont deja justes.
--   mysql -u root dayout < infra/sql/recount_registrations.sql
-- NB : echoue sur chk_events_regcount si un event a plus d'inscrits actifs que sa capacite
-- (incoherence a corriger a la main, la requete de controle ci-dessous la montre).

SET NAMES utf8mb4;

START TRANSACTION;

UPDATE events e
SET e.registered_count = (
  SELECT COUNT(*)
  FROM registrations r
  WHERE r.event_id = e.id
    AND fn_registration_is_active(r.status) = 1
);

COMMIT;

-- Controle : doit renvoyer 0 ligne.
SELECT e.id, e.registered_count, COUNT(r.id) AS real_count
FROM events e
LEFT JOIN registrations r ON r.event_id = e.id AND fn_registration_is_active(r.status) = 1
GROUP BY e.id, e.registered_count
HAVING e.registered_count <> COUNT(r.id);
