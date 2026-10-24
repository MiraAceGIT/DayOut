CREATE TABLE registrations (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id       BIGINT UNSIGNED NOT NULL,
  user_id        BIGINT UNSIGNED NOT NULL,
  status         ENUM('registered','cancelled','attended','no_show') NOT NULL DEFAULT 'registered',
  qr_code_hash   CHAR(64)     NOT NULL,
  registered_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at   DATETIME     NULL,
  checked_in_at  DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_reg_event_user (event_id, user_id),
  UNIQUE KEY uk_reg_qr (qr_code_hash),
  KEY idx_reg_user (user_id),
  KEY idx_reg_status (status),
  CONSTRAINT fk_reg_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reg_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clics billetterie externe : comptent 0.5 participation dans le score de fiabilite.
CREATE TABLE paid_event_clicks (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  clicked_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_click_event_user (event_id, user_id),
  KEY idx_click_user (user_id),
  CONSTRAINT fk_click_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_click_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des variations du score de fiabilite (cf. 08_views.sql).
CREATE TABLE attendance_events (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  event_id    BIGINT UNSIGNED NOT NULL,
  kind        ENUM('attended','no_show','cancelled_late','paid_click','corrected') NOT NULL,
  score_delta SMALLINT     NOT NULL DEFAULT 0,
  weight      DECIMAL(3,2) NOT NULL DEFAULT 1.00,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_att_user (user_id),
  KEY idx_att_event (event_id),
  KEY idx_att_kind (kind),
  CONSTRAINT fk_att_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_att_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pas de user_id : il se deduit via registration_id -> registrations.user_id (JOIN registrations).
CREATE TABLE absence_corrections (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id BIGINT UNSIGNED NOT NULL,
  reason          TEXT         NOT NULL,
  proof_url       VARCHAR(255) NULL,
  status          ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
  reviewed_by     BIGINT UNSIGNED NULL,
  reviewed_at     DATETIME     NULL,
  review_notes    VARCHAR(255) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_absence_registration (registration_id),
  KEY idx_absence_reviewer (reviewed_by),
  CONSTRAINT fk_absence_registration
    FOREIGN KEY (registration_id) REFERENCES registrations(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_absence_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
