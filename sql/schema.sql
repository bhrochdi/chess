-- ============================================================
--  Le Cercle — schéma de la base de données
--  À exécuter une fois pour initialiser le stockage.
--  MySQL 8.0+ :
--    c:/wamp64/bin/mysql/mysql8.0.27/bin/mysql.exe -u root < sql/schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS le_cercle
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE le_cercle;

-- ------------------------------------------------------------
--  Stockage clé-valeur générique — remplace l'API window.storage
--  de l'artefact Claude.ai. Toute l'application transite par cette
--  table via les fonctions front sget / sset / slist / sdel.
--
--  scope = ''                  -> donnée PARTAGÉE (visible de tous)
--  scope = id de session PHP   -> donnée PROPRE au visiteur
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kv_store (
  cle    VARCHAR(200) NOT NULL,
  scope  VARCHAR(64)  NOT NULL DEFAULT '',
  valeur JSON         NOT NULL,
  maj    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (cle, scope),
  KEY idx_scope_cle (scope, cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
