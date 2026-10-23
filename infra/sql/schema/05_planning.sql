-- =============================================================================
-- DayOut — Schema : planning personnel & suggestions
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0 compatible
-- =============================================================================

CREATE TABLE plannings (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    BIGINT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL DEFAULT 'Mon planning',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plannings_user (user_id),
  CONSTRAINT fk_plannings_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planning_items (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  planning_id BIGINT UNSIGNED NOT NULL,
  event_id    BIGINT UNSIGNED NULL,
  title       VARCHAR(180) NULL,
  start_at    DATETIME     NOT NULL,
  end_at      DATETIME     NOT NULL,
  note        VARCHAR(255) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_plan_item_planning_event (planning_id, event_id),
  KEY idx_plan_item_planning (planning_id),
  KEY idx_plan_item_event (event_id),
  CONSTRAINT fk_plan_item_planning
    FOREIGN KEY (planning_id) REFERENCES plannings(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_plan_item_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_plan_item_dates CHECK (end_at > start_at),
  CONSTRAINT chk_plan_item_source CHECK (event_id IS NOT NULL OR title IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE suggestion_dismissals (
  user_id       BIGINT UNSIGNED NOT NULL,
  event_id      BIGINT UNSIGNED NOT NULL,
  dismissed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, event_id),
  KEY idx_sd_event (event_id),
  CONSTRAINT fk_sd_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_sd_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
