<?php
/*
 * Le Cercle — exemple de configuration de la base de données.
 *
 * Copier ce fichier vers « config.php » dans le même dossier, puis
 * adapter les identifiants à ton installation WampServer.
 *
 *   copy api\config.example.php api\config.php
 *
 * IMPORTANT : config.php contient les identifiants et n'est JAMAIS
 * versionné (il est ignoré par git via .gitignore).
 */

return [
    'db_host'    => '127.0.0.1',
    'db_port'    => 3306,
    'db_name'    => 'le_cercle',
    'db_user'    => 'root',
    'db_pass'    => '',          // mot de passe MySQL (vide par défaut sur WampServer)
    'db_charset' => 'utf8mb4',
];
