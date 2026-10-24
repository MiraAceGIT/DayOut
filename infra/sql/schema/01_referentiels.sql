CREATE TABLE schools (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(160) NOT NULL,
  short_name    VARCHAR(32)  NULL,
  email_domain  VARCHAR(120) NOT NULL,
  city          VARCHAR(80)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_schools_domain (email_domain),
  KEY idx_schools_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug       VARCHAR(60)  NOT NULL,
  label      VARCHAR(80)  NOT NULL,
  icon       VARCHAR(40)  NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
