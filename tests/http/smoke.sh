#!/usr/bin/env bash
# Contrôle HTTP de l'application servie : dossiers non publics masqués (404),
# pages publiques servies (200), point d'entrée de santé en JSON.
#   tests/http/smoke.sh http://localhost:8080
set -uo pipefail

base="${1:?usage : tests/http/smoke.sh <url-de-base>}"
base="${base%/}"
failures=0

fail() {
    echo "ÉCHEC : $*"
    failures=$((failures + 1))
}

# expect_status <code> <méthode> <chemin> [corps]
expect_status() {
    local expected="$1" method="$2" path="$3" body="${4-}" actual
    if [[ "$method" == POST ]]; then
        actual=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H 'Content-Type: application/json' --data-binary "$body" "$base$path")
    else
        actual=$(curl -s -o /dev/null -w '%{http_code}' "$base$path")
    fi
    [[ "$actual" == "$expected" ]] || fail "$method $path : $actual au lieu de $expected"
}

hidden=(
    /src/Chess/Color.php /vendor/autoload.php /vendor/composer/installed.json /Vendor/autoload.php
    /tests/Unit/Chess/ColorTest.php /sql/migrations/001-schema-initial.sql /docker/php/Dockerfile
    /bin/migrate.php /build/ /.env /.env.example /.git/HEAD /composer.json /composer.lock
    /docker-compose.yml /phpunit.xml.dist /api/config.php /api/config.example.php /api/config.docker.example.php
    /api/storage.php /public/index.html /README.md
)
for path in "${hidden[@]}"; do
    expect_status 404 GET "$path"
done

# Refusés par Apache lui-même (fichiers .ht*, liste du dossier api/).
expect_status 403 GET /api/.htaccess
expect_status 403 GET /api/

for path in / /index.html /css/styles.css /js/main.js; do
    expect_status 200 GET "$path"
done

headers=$(mktemp)
body=$(curl -s -D "$headers" -X POST -H 'Content-Type: application/json' --data-binary '{"action":"ping"}' "$base/api/health.php")
grep -qi '^HTTP/[0-9.]* 200' "$headers" || fail "POST /api/health.php ping : statut $(head -n1 "$headers" | tr -d '\r')"
grep -qi '^Content-Type: application/json; charset=utf-8' "$headers" || fail "POST /api/health.php ping : Content-Type inattendu"
[[ "$body" == '{"ok":true}' ]] || fail "POST /api/health.php ping : corps « $body »"
rm -f "$headers"

expect_status 405 GET /api/health.php
expect_status 400 POST /api/health.php '{'
expect_status 400 POST /api/health.php '{"action":"nope"}'

jar=$(mktemp)
trap 'rm -f "$jar"' EXIT

# api <chemin> <corps-json> : POST avec les cookies de session partagés ; renseigne $status et $body.
api() {
    local out
    out=$(curl -s -b "$jar" -c "$jar" -w $'\n%{http_code}' -X POST -H 'Content-Type: application/json' --data-binary "$2" "$base$1")
    status="${out##*$'\n'}"
    body="${out%$'\n'*}"
}

# expect_api <code> <chemin> <corps-json> [texte-attendu]
expect_api() {
    api "$2" "$3"
    [[ "$status" == "$1" ]] || { fail "POST $2 $3 : $status au lieu de $1 ($body)"; return; }
    [[ -z "${4-}" || "$body" == *"$4"* ]] || fail "POST $2 $3 : « $4 » absent de $body"
}

new_session() { : >"$jar"; }

register_user() {
    user_pseudo="smk$(printf '%08d' $((RANDOM * RANDOM % 100000000)))"
    expect_api 200 /api/auth.php "{\"action\":\"register\",\"pseudo\":\"$user_pseudo\",\"email\":\"$user_pseudo@exemple.fr\",\"password\":\"motdepasse-smoke\"}" '"ok":true'
}

for check in "$(dirname "${BASH_SOURCE[0]}")"/smoke.d/*.sh; do
    [[ -e "$check" ]] || continue
    # shellcheck source=/dev/null
    source "$check"
done

if ((failures > 0)); then
    echo "$failures contrôle(s) en échec."
    exit 1
fi
echo "Contrôle HTTP : tout est conforme."
