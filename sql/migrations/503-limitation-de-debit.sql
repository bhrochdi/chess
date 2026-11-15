-- ============================================================
--  503 — limitation de débit : un événement compté par ligne
--  (création de compte, envoi de message, création de partie).
--  hit_key : empreinte SHA-256 de la clé (« guest:<adresse> »,
--  « message:<compte> »…) ; ni adresse ni compte en clair.
--  Les lignes de plus de 24 h sont effacées à chaque écriture.
--  Les échecs de connexion gardent leur table (502).
-- ============================================================
CREATE TABLE IF NOT EXISTS throttle_hits (
  id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hit_key CHAR(64)        NOT NULL,
  hit_at  BIGINT          NOT NULL,
  PRIMARY KEY (id),
  KEY idx_throttle_hits_key (hit_key, hit_at),
  KEY idx_throttle_hits_at (hit_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
