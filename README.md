# Le Cercle — Plateforme d'échecs en ligne

Application web d'échecs (jeu local + IA, parties en ligne, salons, messagerie,
tournois, administration), servie en autonomie par **WampServer** (Apache +
PHP + MySQL). Front en HTML/CSS/JS vanilla, sans framework ni dépendance npm.

## Structure du projet

```
index.html                markup + <link>/<script src> (aucun gros bloc inline
                          hormis le mini-script anti-flash de thème)
css/
  styles.css              toute la présentation (thèmes inclus)
js/
  engine.js               moteur d'échecs PUR (aucun DOM) — règles, SAN, IA
  ai-worker.js            Web Worker : importScripts('engine.js') + calcul du coup IA
  app.js                  l'application (vues, BoardUI, stockage, auth, tournois, admin)
  theme-switcher.js       sélecteur de thème (bois/clair/marbre/nocturne)
api/
  storage.php             stockage clé-valeur (kv_store)
  auth.php                authentification serveur (password_hash, sessions PHP)
  config.example.php      modèle de config — copier vers config.php (NON versionné)
sql/
  schema.sql              base le_cercle : kv_store, users, stat_records
tests/
  *.test.js               tests unitaires (round-robin, formats de tournoi)
  _runner-*.html          lanceurs navigateur des tests (si node absent)
docs/
  TESTS-MANUELS.md        plan de test manuel (navigateur + SQL)
```

## Comment ça s'articule

- **Moteur / IA** : `engine.js` est chargé côté page (`<script src>`) **et** dans
  le worker (`ai-worker.js` via `importScripts`). Les scripts sont classiques
  (non-module) et chargés dans l'ordre `engine.js` → `app.js` : ils partagent la
  portée globale, donc `app.js` voit les fonctions/constantes du moteur.
  Si le worker ne peut pas démarrer (ex. `file://`), `app.js` retombe sur un
  calcul IA synchrone.
- **Stockage** : tout passe par `sget/sset/slist/sdel` + le drapeau
  `HAS_STORAGE`. En mode PHP → `api/storage.php` ; en mode artefact Claude.ai →
  `window.storage`. Scopes : partagé (`''`) ou session PHP (données du visiteur).
- **Authentification** : en mode PHP → `api/auth.php` (hachage serveur, sessions
  PHP) ; en mode artefact → base JSON `db:users` (héritage). Le premier compte
  créé devient administrateur.
- **Temps réel** : polling de 2 à 8 s selon les vues (ne pas l'agressiver).

## Installation locale (WampServer)

1. Base de données :
   ```
   c:/wamp64/bin/mysql/<version>/bin/mysql.exe -u root -p < sql/schema.sql
   ```
2. Configuration :
   ```
   copy api\config.example.php api\config.php   # puis adapter les identifiants
   ```
3. Ouvrir `http://localhost/LeCercle/` (Apache doit servir le dossier).

## Tests

- Unitaires (Node) : `node tests/round-robin.test.js` et
  `node tests/tournois-formats.test.js` (doivent afficher « TOUS LES TESTS PASSENT »).
- Sans Node : ouvrir `tests/_runner-rr.html` / `tests/_runner-tf.html` au navigateur.
- Manuel : suivre `docs/TESTS-MANUELS.md`.

## Conventions

Français partout (code, commits, docs). Conventional Commits. Branches
`feat/…` / `fix/…` / `refactor/…`, jamais de commit direct sur `main`.
PDO + requêtes préparées côté PHP ; identifiants jamais versionnés
(`api/config.php` ignoré par git). Ne pas modifier la logique du moteur
(`js/engine.js`) sans tests de non-régression.
