CREATE TABLE users (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email              VARCHAR(190) NOT NULL,
  -- Denormalisation volontaire : = SUBSTRING_INDEX(email,'@',-1), indexe pour la whitelist ecoles.
  -- Maintenu par trg_users_bi_whitelist / trg_users_bu_whitelist ; diverge si on contourne les triggers.
  email_domain       VARCHAR(120) NOT NULL,
  password_hash      VARCHAR(255) NOT NULL,
  first_name         VARCHAR(80)  NOT NULL,
  last_name          VARCHAR(80)  NOT NULL,
  birth_date         DATE         NULL,
  phone              VARCHAR(32)  NULL,
  school_id          INT UNSIGNED NULL,
  role               ENUM('participant','creator','admin') NOT NULL DEFAULT 'participant',
  creator_status     ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none',
  avatar_url         VARCHAR(255) NULL,
  bio                TEXT         NULL,
  email_verified     TINYINT(1)   NOT NULL DEFAULT 0,
  email_verified_at  DATETIME     NULL,
  reliability_score  TINYINT UNSIGNED NOT NULL DEFAULT 100,
  banned             TINYINT(1)   NOT NULL DEFAULT 0,
  banned_reason      VARCHAR(255) NULL,
  last_login_at      DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_users_email (email),
  KEY idx_users_school (school_id),
  KEY idx_users_role (role),
  KEY idx_users_domain (email_domain),
  CONSTRAINT fk_users_school
    FOREIGN KEY (school_id) REFERENCES schools(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_users_score CHECK (reliability_score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)     NOT NULL,
  expires_at  DATETIME     NOT NULL,
  used_at     DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_email_verif_token (token_hash),
  KEY idx_email_verif_user (user_id),
  CONSTRAINT fk_email_verif_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE creator_profiles (
  user_id             BIGINT UNSIGNED NOT NULL,
  display_name        VARCHAR(120) NOT NULL,
  description         TEXT         NULL,
  website_url         VARCHAR(255) NULL,
  instagram_handle    VARCHAR(80)  NULL,
  submitted_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at         DATETIME     NULL,
  reviewed_by         BIGINT UNSIGNED NULL,
  review_notes        VARCHAR(255) NULL,
  PRIMARY KEY (user_id),
  KEY idx_creator_reviewer (reviewed_by),
  CONSTRAINT fk_creator_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_creator_reviewer
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_interests (
  user_id      BIGINT UNSIGNED NOT NULL,
  category_id  INT    UNSIGNED NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, category_id),
  KEY idx_ui_category (category_id),
  CONSTRAINT fk_ui_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ui_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
