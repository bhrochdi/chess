-- ============================================================
--  502 — échecs de connexion récents (vague D).
--  attempt_key : « id: » ou « ip: » suivi de l'empreinte SHA-256
--  de l'identifiant ou de l'adresse ; rien n'est gardé en clair.
--  Les lignes de plus de 24 h sont effacées à chaque écriture.
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
  attempt_key  CHAR(67) NOT NULL,
  attempted_at BIGINT   NOT NULL,
  KEY idx_login_attempts (attempt_key, attempted_at),
  KEY idx_login_attempts_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
