-- add_user_search_radius
-- Ne plus modifier une fois mergee : ecrire une nouvelle migration.

-- Rayon de recherche choisi a l'inscription (SCRUM-41), en km. 3 = rayon par defaut de la carte, 1-20 = bornes du curseur.
ALTER TABLE users
  ADD COLUMN search_radius_km TINYINT UNSIGNED NOT NULL DEFAULT 3 AFTER school_id,
  ADD CONSTRAINT chk_users_radius CHECK (search_radius_km BETWEEN 1 AND 20);
