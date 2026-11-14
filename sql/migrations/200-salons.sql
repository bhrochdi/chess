-- ============================================================
--  200 — salons thématiques et leurs messages (module A).
--  « general » est créé ici : il ne peut être ni archivé ni
--  supprimé (règle appliquée par RoomService).
-- ============================================================
CREATE TABLE IF NOT EXISTS rooms (
  id          VARCHAR(16)  NOT NULL,
  name        VARCHAR(40)  NOT NULL,
  description VARCHAR(200) NOT NULL DEFAULT '',
  position    INT          NOT NULL,
  archived    TINYINT(1)   NOT NULL DEFAULT 0,
  created_by  VARCHAR(32)  DEFAULT NULL,
  created_at  DATETIME(3)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_rooms_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_messages (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  room_id    VARCHAR(16)     NOT NULL,
  author_id  VARCHAR(32)     NOT NULL,
  body       VARCHAR(500)    NOT NULL,
  created_at DATETIME(3)     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_room_messages_room (room_id, id),
  CONSTRAINT fk_room_messages_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO rooms (id, name, description, position, archived, created_by, created_at)
VALUES ('general', 'Général', 'Discussion générale du Cercle', 0, 0, NULL, UTC_TIMESTAMP(3));
