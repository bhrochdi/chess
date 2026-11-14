-- ============================================================
--  100 — parties (locales, contre l'ordinateur, en ligne).
--  Une ligne par partie ; un coup par ligne dans game_moves
--  (la position se reconstruit en rejouant les coups).
--  Pas de clé étrangère vers users : une partie survit à la
--  suppression d'un compte (pseudo « (compte supprimé) »).
-- ============================================================
CREATE TABLE IF NOT EXISTS games (
  id                  VARCHAR(16)  NOT NULL,
  mode                VARCHAR(8)   NOT NULL,
  status              VARCHAR(10)  NOT NULL,
  owner_id            VARCHAR(32)  NOT NULL,
  white_id            VARCHAR(32)  DEFAULT NULL,
  black_id            VARCHAR(32)  DEFAULT NULL,
  ai_level            VARCHAR(8)   DEFAULT NULL,
  tc_base             INT          DEFAULT NULL,
  tc_inc              INT          DEFAULT NULL,
  white_ms            INT          DEFAULT NULL,
  black_ms            INT          DEFAULT NULL,
  clock_running       CHAR(1)      DEFAULT NULL,
  clock_since         DATETIME(3)  DEFAULT NULL,
  draw_offer          CHAR(1)      DEFAULT NULL,
  result              VARCHAR(7)   DEFAULT NULL,
  reason              VARCHAR(12)  DEFAULT NULL,
  created_at          DATETIME(3)  NOT NULL,
  updated_at          DATETIME(3)  NOT NULL,
  PRIMARY KEY (id),
  KEY idx_games_lobby (mode, status, updated_at),
  KEY idx_games_owner (owner_id, status),
  KEY idx_games_white (white_id, status),
  KEY idx_games_black (black_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS game_moves (
  game_id   VARCHAR(16)       NOT NULL,
  ply       SMALLINT UNSIGNED NOT NULL,
  uci       VARCHAR(5)        NOT NULL,
  san       VARCHAR(12)       NOT NULL,
  white_ms  INT               DEFAULT NULL,
  black_ms  INT               DEFAULT NULL,
  played_at DATETIME(3)       NOT NULL,
  PRIMARY KEY (game_id, ply),
  CONSTRAINT fk_game_moves_game FOREIGN KEY (game_id) REFERENCES games (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
