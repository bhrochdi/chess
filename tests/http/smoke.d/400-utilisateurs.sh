# Comptes : session anonyme, invité, inscription, mauvais mot de passe, présence, actions retirées.
# L'empreinte du mot de passe ($2y$…) ne figure dans aucune réponse.
no_hash() { [[ "$body" != *'$2y$'* ]] || fail "POST /api/auth.php $1 : empreinte de mot de passe dans la réponse"; }

new_session
expect_api 200 /api/auth.php '{"action":"me"}' '"user":null'
expect_api 200 /api/auth.php '{"action":"online"}' '"players":['
expect_api 200 /api/auth.php '{"action":"guest"}' '"provider":"guest"'
expect_api 409 /api/auth.php '{"action":"guest"}' 'Tu es déjà connecté.'
new_session
register_user
no_hash register
expect_api 200 /api/auth.php '{"action":"heartbeat"}' '"ok":true'
expect_api 200 /api/auth.php '{"action":"online"}' "\"pseudo\":\"$user_pseudo\""
# Administrateur ou non selon la base : seule l'absence d'empreinte est vérifiée.
api /api/auth.php '{"action":"list"}'
no_hash list
expect_api 200 /api/auth.php '{"action":"logout"}' '"ok":true'
expect_api 403 /api/auth.php "{\"action\":\"login\",\"identifiant\":\"$user_pseudo\",\"password\":\"mauvais-mot\"}" 'Identifiant ou mot de passe incorrect.'
expect_api 200 /api/auth.php "{\"action\":\"login\",\"identifiant\":\"$user_pseudo\",\"password\":\"motdepasse-smoke\"}" "\"pseudo\":\"$user_pseudo\""
no_hash login
expect_api 400 /api/auth.php '{"action":"ssoLogin","provider":"google","email":"a@b.fr","pseudo":"ab"}' 'Action inconnue.'
expect_api 400 /api/auth.php '{"action":"recordResult","gameId":"g1","outcome":"w"}' 'Action inconnue.'
new_session

# Purge des invités : réservée aux administrateurs.
new_session
expect_api 403 /api/auth.php '{"action":"purgeGuests"}' 'Connexion requise.'
