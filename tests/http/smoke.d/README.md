Contrôles HTTP des points d'entrée, chargés par `tests/http/smoke.sh` dans l'ordre des noms, après les contrôles intégrés.
Nom du fichier : `NNN-nom.sh`, dans la plage de la piste (`100-parties.sh`, `200-chat.sh`, `300-tournois.sh`, `400-utilisateurs.sh`, `500-admin.sh`).
Le fichier est sourcé : il partage `$base`, `fail` et `failures`, et dispose de `api <chemin> <corps-json>`, `expect_api <code> <chemin> <corps-json> [texte]`, `new_session` et `register_user`.
`api` renseigne `$status` et `$body` ; `register_user` crée un compte `smk…` connecté et renseigne `$user_pseudo`.
Un contrôle ne dépend de l'état laissé par aucun autre fichier : il crée ses comptes avec `register_user`.
Un contrôle ne suppose jamais qu'un compte issu de `register_user` est ou n'est pas administrateur (le premier compte d'une base neuve le devient) : les refus réservés aux administrateurs relèvent des tests de contrat des contrôleurs. Un contrôle n'écrase pas les variables du lanceur (`jar`, `failures`, `status`, `body`, `check`, `base`).
