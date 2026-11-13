-- ============================================================
--  202 — commentaires en direct sur une partie. Pas de clé
--  étrangère vers games (table de la piste B2) : l'existence
--  de la partie sera contrôlée en vague C.
-- ============================================================
CREATE TABLE IF NOT EXISTS game_comments (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  game_id    VARCHAR(16)     NOT NULL,
  author_id  VARCHAR(32)     NOT NULL,
  body       VARCHAR(500)    NOT NULL,
  created_at DATETIME(3)     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_game_comments_game (game_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
