# Le Cercle — Plateforme d'échecs en ligne

## Contexte du projet
Parti d'un prototype monofichier développé avec Claude.ai, devenu une
application autonome hébergée sur WampServer : front éclaté en
`index.html` + `css/` + `js/`, backend PHP/MySQL (`api/`).

## Stack
- Front : HTML/CSS/JS vanilla — aucun framework, aucune dépendance npm côté front
- Back (cible) : PHP 8.x avec PDO (requêtes préparées obligatoires), MySQL 8.0.27
- Serveur local : WampServer (Apache), projet servi depuis `c:/wamp64/www/le-cercle/`
  (adapter le chemin si besoin)

## Architecture actuelle (à lire avant toute modification)
Depuis `refactor/decoupage`, le front est éclaté (plus de monofichier) :
`index.html` (markup) + `css/styles.css` + `js/` (voir `README.md`).
- `js/engine.js` : moteur d'échecs PUR (aucune référence au DOM). Il est
  réutilisé tel quel — chargé dans `js/ai-worker.js` via `importScripts` pour
  l'IA — ne jamais y introduire de code DOM. Moteur testé et validé : ne pas
  le modifier sans tests de non-régression (20 coups en position initiale, mat
  du berger, roque, prise en passant ; cf. `tests/`).
- `js/app.js` : l'application — 6 vues (Accueil, Jeu local, Partie en ligne,
  Salon, Messages, Tournois) + espace Admin, rendu d'échiquier générique
  `BoardUI`, authentification (serveur en mode PHP via `api/auth.php`, base
  JSON `db:users` en mode artefact). `js/theme-switcher.js` gère le thème.
  Scripts classiques chargés dans l'ordre engine.js → app.js (portée globale
  partagée).
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
4. `feat/admin-salons` — gestion de plusieurs salons thématiques (cf. ci-dessous)
5. `feat/admin-tournois` — système de tournois administrable (cf. ci-dessous)
6. `refactor/decoupage` — éclater le monofichier (css/, js/, moteur séparé)

---

## Espace administrateur — extensions à développer

> Rappel : l'espace admin de base existe déjà (tableau de bord, gestion des
> utilisateurs avec suspension/rôles/suppression, modération du salon unique,
> journal d'administration, maintenance/sauvegarde JSON). Les deux modules
> ci-dessous sont des AJOUTS. Réutiliser l'onglet « ⚙ Admin » existant en y
> ajoutant des sous-sections, et réutiliser les briques en place :
> `isAdmin()`, `adminLog()`, `askConfirm()`, `sget/sset/slist/sdel`,
> les styles `.admin-table`, `.stat-card`, `.dangerzone`.

### Module A — Gestion des salons (`feat/admin-salons`)
Objectif : passer du salon unique (`chat:global`) à plusieurs salons
thématiques administrables (ex. « Général », « Débutants », « Analyses »,
« Tournois »).

Modèle de données (stockage partagé) :
- `db:rooms` : tableau de salons
  `{ id, nom, description, ordre, archive: bool, creePar, creeLe }`
- `chat:room:<id>` : tableau de messages du salon (même format que l'actuel
  `chat:global` — prévoir une migration de `chat:global` vers
  `chat:room:general`).

Côté admin (sous-section « Salons ») :
- Créer un salon (nom + description), renommer, réordonner, archiver/désarchiver,
  supprimer (avec `askConfirm` + purge de `chat:room:<id>`).
- Modération par salon (réutiliser le rendu existant, paramétré par salon).
- Tracer chaque action via `adminLog()`.

Côté utilisateur (vue Salon) :
- Sélecteur de salon (liste déroulante ou onglets) ; n'afficher que les salons
  non archivés. Le polling et les badges de nouveaux messages doivent suivre le
  salon actif.

Garde-fous : interdire la suppression du salon « Général » par défaut ;
nom de salon unique ; échapper toutes les sorties (`esc`).

### Module B — Système de tournois (`feat/admin-tournois`)
Objectif : permettre à un admin de créer et piloter des tournois, et aux
joueurs de s'y inscrire et d'y jouer via le moteur de parties en ligne existant.

Modèle de données (stockage partagé) :
- `db:tournaments` : index léger
  `{ id, nom, format, cadence, statut, nbInscrits, debutLe }`
- `tournament:<id>` : détail complet
  `{ id, nom, description, format: 'round-robin'|'elimination'|'suisse',
     cadence (réutiliser le format tc {base,inc}|null), statut:
     'inscriptions'|'en-cours'|'termine', participants: [pseudo...],
     rondes: [ { numero, matchs: [ { blanc, noir, gameId|null,
     resultat: '1-0'|'0-1'|'1/2-1/2'|null } ] } ],
     classement: [ { pseudo, points, joues } ], creePar, creeLe }`

Logique de tournoi (commencer SIMPLE) :
- Étape 1 — round-robin (toutes rondes) uniquement : génération de l'appariement
  (algorithme du cercle/berger), au moins un test unitaire sur la génération.
- Étape 2 — barème : victoire 1, nulle 0,5, défaite 0 ; classement trié
  points puis départage simple (ex. nombre de victoires).
- Étape 3 — élimination directe et système suisse dans un second temps.

Intégration avec l'existant (réutiliser, ne pas réécrire) :
- Un match crée une partie via le mécanisme `game:<id>` déjà en place, avec un
  lien retour `tournamentId`/`rondeId` pour reporter automatiquement le résultat
  dans le tournoi à la fin de la partie (brancher sur la finalisation existante
  qui écrit `R.result`).
- Les pendules, l'abandon, la nulle, le PGN fonctionnent déjà : ne rien
  dupliquer.

Côté admin (sous-section « Tournois ») :
- Créer un tournoi (nom, description, format, cadence), ouvrir/fermer les
  inscriptions, lancer (génère la 1re ronde), saisir/corriger un résultat,
  passer à la ronde suivante, clore. Tracer via `adminLog()`.

Côté utilisateur :
- Liste des tournois (par statut), inscription/désinscription tant que les
  inscriptions sont ouvertes, affichage de l'arbre/grille et du classement,
  accès direct à sa partie de la ronde en cours.

Garde-fous : pas de modification du statut incohérente (ex. lancer sans 2
inscrits) ; un seul tournoi `en-cours` à la fois côté UI au début (simplifie) ;
réutiliser `BoardUI` et la synchro de partie sans les altérer.

### Contraintes communes aux deux modules
- Conserver l'abstraction de stockage : aucun accès direct à `window.storage`
  hors de `sget/sset/slist/sdel` (compatibilité artefact + futur backend PHP).
- Ne pas modifier le moteur d'échecs (`#engineCode`).
- Conserver les cadences de polling existantes.
- Commits atomiques en Conventional Commits français, une fonctionnalité par
  branche.
- Prévoir la version PHP/MySQL : ces nouvelles clés (`db:rooms`,
  `chat:room:*`, `db:tournaments`, `tournament:*`) devront mapper sur le même
  `kv_store` que le reste — donc rien de spécial à prévoir si l'abstraction est
  respectée.
