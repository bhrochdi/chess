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

-- ------------------------------------------------------------
--  Comptes utilisateurs — authentification côté serveur.
--  Le mot de passe n'est stocké QUE haché (password_hash). Les
--  colonnes legacy_* ne servent qu'à la migration depuis l'ancienne
--  base JSON db:users (hachage client) ; elles sont effacées dès la
--  première connexion réussie (ré-hachage en password_hash).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id          VARCHAR(32)  NOT NULL,
  pseudo      VARCHAR(16)  NOT NULL,
  email       VARCHAR(120) DEFAULT NULL,
  provider    VARCHAR(20)  NOT NULL DEFAULT 'local',
  role        VARCHAR(10)  NOT NULL DEFAULT 'user',
  pass_hash   VARCHAR(255) DEFAULT NULL,
  legacy_salt VARCHAR(64)  DEFAULT NULL,
  legacy_hash VARCHAR(128) DEFAULT NULL,
  banned      TINYINT(1)   NOT NULL DEFAULT 0,
  stats       JSON         NOT NULL,
  created_at  BIGINT       NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_pseudo (pseudo),
  UNIQUE KEY uniq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
--  Comptabilisation des résultats : une partie ne compte qu'une
--  fois par joueur (idempotence des statistiques V/D/N).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stat_records (
  game_id VARCHAR(40) NOT NULL,
  user_id VARCHAR(32) NOT NULL,
  PRIMARY KEY (game_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
