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
# Relecture : la version affichée répond « unchanged », une version périmée renvoie la partie.
expect_api 200 /api/games.php "{\"action\":\"get\",\"id\":\"$game_id\"}" '"version":"'
game_version=$(grep -o '"version":"[^"]*"' <<<"$body" | head -n1 | cut -d'"' -f4)
expect_api 200 /api/games.php "{\"action\":\"get\",\"id\":\"$game_id\",\"known\":\"$game_version\"}" '{"ok":true,"unchanged":true}'
expect_api 200 /api/games.php "{\"action\":\"get\",\"id\":\"$game_id\",\"known\":\"perimee\"}" '"moves":["e4","'
# Relecture coup par coup et historique (une partie contre l'ordinateur n'y figure pas).
expect_api 200 /api/games.php "{\"action\":\"replay\",\"id\":\"$game_id\"}" '"frames":[{"fen":"rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1"'
expect_api 200 /api/games.php '{"action":"history"}' '"games":[],"more":false'
expect_api 422 /api/games.php '{"action":"history","offset":-1}' 'Page d’historique invalide.'
expect_api 404 /api/games.php '{"action":"get","id":"g000000000000"}' 'Partie introuvable.'
# Débit des défis : 5 par compte en 10 minutes, le suivant est refusé.
new_session
register_user
for _ in 1 2 3 4 5; do
    expect_api 200 /api/games.php '{"action":"create","mode":"online","tc":null}' '"status":"open"'
done
expect_api 429 /api/games.php '{"action":"create","mode":"online","tc":null}' 'Tu as créé trop de défis.'
new_session
