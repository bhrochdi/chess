-- ============================================================
--  002 — journal des actions d'administration (vague B).
--  Écrit par les services (salons, tournois, utilisateurs) via
--  le port Shared\AdminJournal ; lu par l'espace admin (vague C).
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id   VARCHAR(32)     NOT NULL,
  action     VARCHAR(500)    NOT NULL,
  created_at DATETIME(3)     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_admin_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
