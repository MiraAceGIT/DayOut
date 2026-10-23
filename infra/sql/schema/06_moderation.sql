-- =============================================================================
-- DayOut — Schema : interactions sociales & modération
-- Target: MariaDB 10.4+ (XAMPP) / MySQL 8.0 compatible
-- =============================================================================

CREATE TABLE event_messages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  body        VARCHAR(1000) NOT NULL,
  edited_at   DATETIME     NULL,
  deleted_at  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_em_event (event_id),
  KEY idx_em_user (user_id),
  CONSTRAINT fk_em_event
    FOREIGN KEY (event_id) REFERENCES events(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_em_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id    BIGINT UNSIGNED NOT NULL,
  target_type    ENUM('event','user','message') NOT NULL,
  target_event_id    BIGINT UNSIGNED NULL,
  target_user_id     BIGINT UNSIGNED NULL,
  target_message_id  BIGINT UNSIGNED NULL,
  reason         VARCHAR(80) NOT NULL,
  details        TEXT        NULL,
  status         ENUM('open','in_review','resolved','rejected') NOT NULL DEFAULT 'open',
  created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_reporter (reporter_id),
  KEY idx_reports_target_event (target_event_id),
  KEY idx_reports_target_user  (target_user_id),
  KEY idx_reports_target_msg   (target_message_id),
  KEY idx_reports_status (status),
  CONSTRAINT fk_reports_reporter
    FOREIGN KEY (reporter_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reports_event
    FOREIGN KEY (target_event_id) REFERENCES events(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reports_user
    FOREIGN KEY (target_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_reports_message
    FOREIGN KEY (target_message_id) REFERENCES event_messages(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_report_target CHECK (
    (target_type = 'event'   AND target_event_id   IS NOT NULL AND target_user_id IS NULL AND target_message_id IS NULL) OR
    (target_type = 'user'    AND target_user_id    IS NOT NULL AND target_event_id IS NULL AND target_message_id IS NULL) OR
    (target_type = 'message' AND target_message_id IS NOT NULL AND target_event_id IS NULL AND target_user_id    IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE report_actions (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  report_id  BIGINT UNSIGNED NOT NULL,
  admin_id   BIGINT UNSIGNED NOT NULL,
  action     ENUM('warn','hide_message','unpublish_event','ban_user','dismiss','note') NOT NULL,
  comment    VARCHAR(500) NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ra_report (report_id),
  KEY idx_ra_admin (admin_id),
  CONSTRAINT fk_ra_report
    FOREIGN KEY (report_id) REFERENCES reports(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_ra_admin
    FOREIGN KEY (admin_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
