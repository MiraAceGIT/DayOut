-- add_rate_limit_hits
-- Ne plus modifier une fois mergee : ecrire une nouvelle migration.

-- Limitation de debit par adresse IP (SCRUM-41 : inscriptions, confirmations). Une ligne par requete comptee ;
-- key_hash = SHA-256 de l'IP (pas d'adresse en clair). Purge des lignes plus vieilles que la fenetre a chaque ajout.
CREATE TABLE rate_limit_hits (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket    VARCHAR(40) NOT NULL,
  key_hash  CHAR(64)    NOT NULL,
  hit_at    DATETIME    NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rate_limit_hits (bucket, key_hash, hit_at),
  KEY idx_rate_limit_hits_at (hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
