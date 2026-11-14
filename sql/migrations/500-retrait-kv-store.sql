-- ============================================================
--  500 — retrait de l'ancien stockage clé-valeur (vague D).
--  kv_store servait api/storage.php ; stat_records comptait les
--  statistiques, désormais calculées à partir des parties.
--  Nouveau départ : aucune donnée n'est reprise.
-- ============================================================
DROP TABLE IF EXISTS kv_store;
DROP TABLE IF EXISTS stat_records;
