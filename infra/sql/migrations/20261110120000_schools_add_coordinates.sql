-- schools_add_coordinates
-- Ne plus modifier une fois mergee : ecrire une nouvelle migration.

-- D8 Transports : point de depart "campus de l'ecole" pour l'itineraire vers un event.
-- Memes type/precision que events.latitude/longitude pour rester coherent.
ALTER TABLE schools
  ADD COLUMN latitude  DECIMAL(9,6) NULL AFTER city,
  ADD COLUMN longitude DECIMAL(9,6) NULL AFTER latitude;
