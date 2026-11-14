-- ============================================================
--  400 — présence côté serveur : dernière activité d'un compte
--  (remplace la clé partagée « presence » du stockage clé-valeur).
-- ============================================================
ALTER TABLE users
  ADD COLUMN last_seen_at DATETIME(3) DEFAULT NULL,
  ADD KEY idx_users_last_seen (last_seen_at);
