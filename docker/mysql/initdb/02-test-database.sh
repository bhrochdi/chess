#!/bin/sh
# Crée la base des tests d'intégration et donne les droits à l'utilisateur applicatif.
set -eu
mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS le_cercle_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON le_cercle_test.* TO '$MYSQL_USER'@'%';
SQL
