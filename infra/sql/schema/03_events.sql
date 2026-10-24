CREATE TABLE events (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  creator_id           BIGINT UNSIGNED NOT NULL,
  title                VARCHAR(180) NOT NULL,
  slug                 VARCHAR(200) NOT NULL,
  description          TEXT         NOT NULL,
  cover_image_url      VARCHAR(255) NULL,
  start_at             DATETIME     NOT NULL,
  end_at               DATETIME     NOT NULL,
  venue_name           VARCHAR(160) NULL,
  address_line         VARCHAR(200) NULL,
  city                 VARCHAR(80)  NULL,
  postal_code          VARCHAR(20)  NULL,
  latitude             DECIMAL(9,6) NULL,
  longitude            DECIMAL(9,6) NULL,
  capacity             INT UNSIGNED NULL,
  -- Denormalisation volontaire : = COUNT(registrations actives), evite un COUNT par carte du feed.
  -- Active = status <> 'cancelled' (fn_registration_is_active). Maintenu par trg_registrations_ai/au/ad
  -- et trg_users_bd_registrations.
  registered_count     INT UNSIGNED NOT NULL DEFAULT 0,
  price_cents          INT UNSIGNED NOT NULL DEFAULT 0,
  currency             CHAR(3)      NOT NULL DEFAULT 'EUR',
  is_paid              TINYINT(1)   NOT NULL DEFAULT 0,
  external_ticket_url  VARCHAR(255) NULL,
  -- Denormalisation volontaire : = (restricted_school_id IS NOT NULL), pour des filtres lisibles.
  -- Coherence imposee par trg_events_bi / trg_events_bu.
  school_restricted    TINYINT(1)   NOT NULL DEFAULT 0,
  restricted_school_id INT UNSIGNED NULL,
  status               ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',
  published_at         DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_events_slug (slug),
  KEY idx_events_creator (creator_id),
  KEY idx_events_start (start_at),
  -- Feed public : WHERE status='published' ORDER BY start_at, id : filtre + tri par l'index, sans filesort.
  KEY idx_events_status_start (status, start_at, id),
  KEY idx_events_school (restricted_school_id),
  KEY idx_events_city (city),
  CONSTRAINT fk_events_creator
    FOREIGN KEY (creator_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  -- RESTRICT : un event restreint ne doit jamais redevenir public en silence (SET NULL
  -- laissait school_restricted=1 sans ecole). Ecoles : desactiver, ne pas supprimer.
  CONSTRAINT fk_events_school
    FOREIGN KEY (restricted_school_id) REFERENCES schools(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_events_dates    CHECK (end_at > start_at),
  -- is_paid=1 impose un prix strictement positif (sinon utiliser is_paid=0).
  CONSTRAINT chk_events_price    CHECK ( (is_paid = 0 AND price_cents = 0) OR (is_paid = 1 AND price_cents > 0) ),
  CONSTRAINT chk_events_capacity CHECK (capacity IS NULL OR capacity > 0),
  CONSTRAINT chk_events_regcount CHECK (capacity IS NULL OR registered_count <= capacity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_categories (
  event_id    BIGINT UNSIGNED NOT NULL,
  category_id INT    UNSIGNED NOT NULL,
  PRIMARY KEY (event_id, category_id),
  KEY idx_ec_category (category_id),
  CONSTRAINT fk_ec_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ec_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
