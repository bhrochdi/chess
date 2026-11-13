#!/usr/bin/env bash
# Test de fumée navigateur (sans argument, code 0 = aucune erreur).
# Pile Docker dédiée (projet lecercle-smoke, aucun port publié), toujours détruite à la fin.
# Nécessite .env (cp .env.example .env).
set -eu

cd "$(dirname "$0")/../.."
mkdir -p build/smoke
P="docker compose -p lecercle-smoke -f docker-compose.yml -f docker/smoke/compose.yml"
trap '$P down -v --remove-orphans' EXIT

$P up -d --build --wait db web
$P exec -T web php bin/migrate.php
$P run --rm --build smoke
