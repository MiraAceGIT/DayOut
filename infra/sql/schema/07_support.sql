CREATE TABLE faq_entries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question    VARCHAR(255) NOT NULL,
  answer      TEXT         NOT NULL,
  sort_order  INT UNSIGNED NOT NULL DEFAULT 0,
  active      TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faq_active_sort (active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE legal_pages (
  slug        VARCHAR(60)  NOT NULL,
  title       VARCHAR(160) NOT NULL,
  body_md     MEDIUMTEXT   NOT NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NULL,
  email       VARCHAR(190) NOT NULL,
  subject     VARCHAR(160) NOT NULL,
  body        TEXT         NOT NULL,
  handled     TINYINT(1)   NOT NULL DEFAULT 0,
  handled_at  DATETIME     NULL,
  handled_by  BIGINT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contact_user (user_id),
  KEY idx_contact_handler (handled_by),
  KEY idx_contact_handled (handled),
  CONSTRAINT fk_contact_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_contact_handler
    FOREIGN KEY (handled_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
