# Discussions : salons publics, envoi connecté, messages privés, commentaires.
new_session
expect_api 200 /api/rooms.php '{"action":"list"}' '"id":"general"'
expect_api 403 /api/rooms.php '{"action":"send","roomId":"general","text":"x"}' 'Connexion requise.'
expect_api 404 /api/game-comments.php '{"action":"list","gameId":"pas-une-partie"}' 'Partie introuvable.'
register_user
expect_api 200 /api/rooms.php '{"action":"send","roomId":"general","text":"Bonjour du contrôle HTTP"}' '"text":"Bonjour du contrôle HTTP"'
expect_api 200 /api/rooms.php '{"action":"messages","roomId":"general"}' 'Bonjour du contrôle HTTP'
expect_api 200 /api/direct-messages.php '{"action":"threads"}' '"unreadTotal":'
expect_api 200 /api/games.php '{"action":"create","mode":"online","tc":null}' '"status":"open"'
comment_game=$(grep -o '"id":"g[0-9a-f]\{12\}"' <<<"$body" | head -n1 | cut -d'"' -f4)
expect_api 200 /api/game-comments.php "{\"action\":\"send\",\"gameId\":\"$comment_game\",\"text\":\"Joli coup\"}" '"text":"Joli coup"'
expect_api 404 /api/game-comments.php '{"action":"send","gameId":"g0123456789ab","text":"Joli coup"}' 'Partie introuvable.'
new_session
