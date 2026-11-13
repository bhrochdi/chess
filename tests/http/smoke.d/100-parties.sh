# Parties : refus anonyme, salon public, partie contre l'ordinateur, coup illégal, partie inconnue.
new_session
expect_api 403 /api/games.php '{"action":"create","mode":"local"}' 'Connexion requise.'
expect_api 200 /api/games.php '{"action":"list"}' '"games":['
register_user
expect_api 200 /api/games.php '{"action":"create","mode":"ai","level":"ai-2","tc":null}' '"status":"playing"'
game_id=$(grep -o '"id":"g[0-9a-f]\{12\}"' <<<"$body" | head -n1 | cut -d'"' -f4)
expect_api 200 /api/games.php "{\"action\":\"move\",\"id\":\"$game_id\",\"uci\":\"e2e4\",\"ply\":0}" '"moves":["e4","'
expect_api 422 /api/games.php "{\"action\":\"move\",\"id\":\"$game_id\",\"uci\":\"e2e5\",\"ply\":2}" 'Coup illégal.'
expect_api 200 /api/games.php "{\"action\":\"pgn\",\"id\":\"$game_id\"}" '"filename":"le-cercle-'
expect_api 404 /api/games.php '{"action":"get","id":"g000000000000"}' 'Partie introuvable.'
new_session
