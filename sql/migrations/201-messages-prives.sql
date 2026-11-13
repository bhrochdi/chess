-- ============================================================
--  201 — messages privés entre deux joueurs et dernier message
--  lu par conversation (compteur de non-lus).
-- ============================================================
CREATE TABLE IF NOT EXISTS direct_messages (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id    VARCHAR(32)     NOT NULL,
  recipient_id VARCHAR(32)     NOT NULL,
  body         VARCHAR(500)    NOT NULL,
  created_at   DATETIME(3)     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_dm_sender (sender_id, recipient_id, id),
  KEY idx_dm_recipient (recipient_id, sender_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dm_reads (
  user_id      VARCHAR(32)     NOT NULL,
  peer_id      VARCHAR(32)     NOT NULL,
  last_read_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, peer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
