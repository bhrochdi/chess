-- ============================================================
--  501 — colonnes de users devenues inutiles (vague D).
--  stats : les statistiques sont calculées à partir des parties.
--  legacy_salt / legacy_hash : ancienne base JSON, jamais lues
--  depuis le départ à neuf.
-- ============================================================
ALTER TABLE users
  DROP COLUMN stats,
  DROP COLUMN legacy_salt,
  DROP COLUMN legacy_hash;
