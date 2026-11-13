<?php
/*
 * Le Cercle — configuration pour l'environnement Docker.
 *
 * Copier ce fichier vers « config.php » : les identifiants sont lus
 * depuis les variables d'environnement définies par docker-compose.yml
 * (elles-mêmes issues de .env, non versionné).
 *
 *   cp api/config.docker.example.php api/config.php
 */

return [
    'db_host'    => getenv('DB_HOST') ?: 'db',
    'db_port'    => 3306,
    'db_name'    => getenv('DB_NAME') ?: 'le_cercle',
    'db_user'    => getenv('DB_USER') ?: '',
    'db_pass'    => getenv('DB_PASS') ?: '',
    'db_charset' => 'utf8mb4',
];
