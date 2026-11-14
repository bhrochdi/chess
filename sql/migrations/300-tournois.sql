-- ============================================================
--  300 — tournois (module B) : tournoi, inscrits (ordre
--  d'inscription = têtes de série), appariements par ronde.
--  game_id reste NULL en vague B (lien parties en vague C).
--  Le classement n'est pas stocké : il se calcule à la lecture.
-- ============================================================
CREATE TABLE IF NOT EXISTS tournaments (
  id                VARCHAR(16)       NOT NULL,
  name              VARCHAR(60)       NOT NULL,
  description       VARCHAR(500)      NOT NULL DEFAULT '',
  format            VARCHAR(12)       NOT NULL,
  tc_base           INT               DEFAULT NULL,
  tc_inc            INT               DEFAULT NULL,
  status            VARCHAR(12)       NOT NULL,
  registration_open TINYINT(1)        NOT NULL,
  planned_rounds    TINYINT UNSIGNED  DEFAULT NULL,
  current_round     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_by        VARCHAR(32)       NOT NULL,
  created_at        DATETIME(3)       NOT NULL,
  started_at        DATETIME(3)       DEFAULT NULL,
  finished_at       DATETIME(3)       DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_tournaments_name (name),
  KEY idx_tournaments_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_players (
  tournament_id VARCHAR(16)       NOT NULL,
  user_id       VARCHAR(32)       NOT NULL,
  seq           SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (tournament_id, user_id),
  KEY idx_tournament_players_seq (tournament_id, seq),
  CONSTRAINT fk_tournament_players FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tournament_pairings (
  tournament_id VARCHAR(16)       NOT NULL,
  round         SMALLINT UNSIGNED NOT NULL,
  board         SMALLINT UNSIGNED NOT NULL,
  white_id      VARCHAR(32)       DEFAULT NULL,
  black_id      VARCHAR(32)       DEFAULT NULL,
  result        VARCHAR(7)        DEFAULT NULL,
  game_id       VARCHAR(16)       DEFAULT NULL,
  PRIMARY KEY (tournament_id, round, board),
  KEY idx_tournament_pairings_game (game_id),
  CONSTRAINT fk_tournament_pairings FOREIGN KEY (tournament_id) REFERENCES tournaments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
