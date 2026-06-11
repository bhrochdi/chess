# Le Cercle — Plateforme d'échecs en ligne

## Contexte du projet
Prototype monofichier (`le-cercle-echecs.html`, ~2 400 lignes HTML/CSS/JS vanilla)
développé avec Claude.ai, à faire évoluer vers une application autonome
hébergée sur WampServer.

## Stack
- Front : HTML/CSS/JS vanilla — aucun framework, aucune dépendance npm côté front
- Back (cible) : PHP 8.x avec PDO (requêtes préparées obligatoires), MySQL 8.0.27
- Serveur local : WampServer (Apache), projet servi depuis `c:/wamp64/www/le-cercle/`
  (adapter le chemin si besoin)

## Architecture actuelle (à lire avant toute modification)
- `<script id="engineCode">` : moteur d'échecs PUR (aucune référence au DOM).
  Il est réutilisé tel quel dans un Web Worker pour l'IA — ne jamais y
  introduire de code DOM. Moteur testé et validé : ne pas le modifier sans
  tests de non-régression (20 coups en position initiale, mat du berger,
  roque, prise en passant).
- Second `<script>` : l'application — 5 vues (Accueil, Jeu local, Partie en
  ligne, Salon, Messages) + espace Admin, rendu d'échiquier générique
  `BoardUI`, authentification avec base JSON `db:users`.
- **Point d'abstraction clé** : TOUT l'accès au stockage passe par 4 fonctions
  — `sget`, `sset`, `slist`, `sdel` — et le flag `HAS_STORAGE`. C'est le seul
  endroit où brancher un backend.
- Scopes de stockage : `shared=true` (données communes à tous) /
  `shared=false` (données propres au visiteur → côté PHP : scoper par
  session).
- Le « temps réel » est un polling (2 à 8 s selon les vues) : conserver ces
  cadences, ne pas les agressiver.

## Conventions
- Langue : tout en français (commentaires, commits, documentation)
- Commits : Conventional Commits en français (`feat:`, `fix:`, `refactor:`,
  `docs:`, `chore:`) — commits atomiques
- Branches : `feat/...`, `fix/...` — jamais de commit direct sur `main`
- Sécurité : PDO + requêtes préparées partout ; jamais de credentials en dur
  dans le code versionné (`api/config.php` ignoré par git +
  `api/config.example.php` versionné) ; toujours échapper les sorties HTML
  (la fonction `esc()` existe déjà côté front)
- Pas de réécriture massive non demandée : modifications ciblées

## Règles de travail
1. Lire entièrement `le-cercle-echecs.html` avant la première modification.
2. Ne pas casser la compatibilité artefact Claude.ai : si `window.storage`
   existe, l'utiliser ; sinon basculer sur l'API PHP (fallback).
3. Après chaque étape : vérifier la syntaxe, tester, puis committer.
4. Poser des questions avant toute décision structurante non couverte ici.

## Commandes utiles
- MySQL : `c:/wamp64/bin/mysql/mysql8.0.27/bin/mysql.exe -u root`
- Base de données : `le_cercle` (à créer si absente, cf. `sql/schema.sql`)
- URL locale : `http://localhost/le-cercle/`

## Feuille de route
1. `feat/backend-php` — endpoint `api/storage.php` + fallback front (en cours)
2. `feat/auth-serveur` — hachage `password_hash()`/`password_verify()` côté
   serveur, sessions PHP
3. `feat/oauth-google` — vrai OAuth (Google Identity Services ou Hybridauth)
4. `refactor/decoupage` — éclater le monofichier (css/, js/, moteur séparé)
