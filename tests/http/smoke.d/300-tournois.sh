# Tournois : liste publique, tournoi inconnu, inscription refusée sans connexion.
new_session
expect_api 200 /api/tournaments.php '{"action":"list"}' '"tournaments":['
expect_api 404 /api/tournaments.php '{"action":"get","id":"t000000000000"}' 'Tournoi introuvable.'
expect_api 403 /api/tournaments.php '{"action":"register","id":"t000000000000"}' 'Connexion requise.'
expect_api 403 /api/tournaments.php '{"action":"create","name":"Open","format":"round-robin","tc":null}' 'Connexion requise.'
