-- =============================================================================
-- DayOut — Schema : vue score de fiabilité (colonne + journal)
-- -----------------------------------------------------------------------------
-- Approche hybride :
--  - users.reliability_score = valeur courante denormalisee (0..100)
--  - attendance_events      = journal detaille (audit, debuggable, rejouable)
--  - v_user_reliability     = agregation en temps reel pour analyse admin
-- =============================================================================

CREATE OR REPLACE VIEW v_user_reliability AS
SELECT
  u.id                                                           AS user_id,
  u.email,
  CONCAT(u.first_name, ' ', u.last_name)                         AS full_name,
  u.reliability_score                                            AS current_score,
  COALESCE(SUM(CASE WHEN ae.kind = 'attended'       THEN 1 ELSE 0 END), 0) AS nb_attended,
  COALESCE(SUM(CASE WHEN ae.kind = 'no_show'        THEN 1 ELSE 0 END), 0) AS nb_no_show,
  COALESCE(SUM(CASE WHEN ae.kind = 'cancelled_late' THEN 1 ELSE 0 END), 0) AS nb_cancelled_late,
  COALESCE(SUM(CASE WHEN ae.kind = 'paid_click'     THEN 1 ELSE 0 END), 0) AS nb_paid_clicks,
  COALESCE(SUM(CASE WHEN ae.kind = 'corrected'      THEN 1 ELSE 0 END), 0) AS nb_corrections,
  COALESCE(SUM(ae.score_delta), 0)                               AS cumulative_delta,
  COALESCE(SUM(
    CASE
      WHEN ae.kind = 'attended'   THEN 1.0 * ae.weight
      WHEN ae.kind = 'paid_click' THEN 0.5 * ae.weight
      ELSE 0
    END
  ), 0)                                                          AS participation_count
FROM users u
LEFT JOIN attendance_events ae ON ae.user_id = u.id
GROUP BY u.id, u.email, u.first_name, u.last_name, u.reliability_score;
