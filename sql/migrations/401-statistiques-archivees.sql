-- ============================================================
--  401 — statistiques archivées : résultats des parties en
--  ligne purgées, cumulés par compte. Les statistiques d'un
--  compte sont la somme de ses parties terminées encore en base
--  et de cette ligne ; la purge des parties ne les efface plus.
--  La ligne part avec le compte (clé étrangère).
-- ============================================================
CREATE TABLE IF NOT EXISTS archived_stats (
  user_id VARCHAR(32)  NOT NULL,
  wins    INT UNSIGNED NOT NULL DEFAULT 0,
  losses  INT UNSIGNED NOT NULL DEFAULT 0,
  draws   INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_archived_stats_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
