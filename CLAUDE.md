# Le Cercle — plateforme d'échecs en ligne

Site d'un club d'échecs : jeu contre l'ordinateur, à deux sur un écran ou en
ligne, salons et messages privés, tournois, espace d'administration.

## Principe directeur

**Le serveur décide, le navigateur affiche.** Coups légaux, pendule, résultats,
appariements et droits n'existent qu'en PHP. Le navigateur envoie une intention
et dessine l'état reçu : ne jamais y remettre une règle métier.

## Stack

- Front (`public/`) : HTML/CSS/JS sans framework ni dépendance npm, en scripts
  classiques à portée globale. L'ordre de chargement est fixé par `index.html` :
  un fichier ne voit que ce que déclarent ceux chargés avant lui.
- Back : PHP ≥ 8.3, PDO, MySQL 8. Aucune dépendance Composer à l'exécution
  (PHPUnit, PHPStan et PHP-CS-Fixer en développement seulement).
- Hébergement : WampServer (hôte virtuel `le-cercle.localhost`, racine
  `public/`, `api/` servi sous `/api`) ou Docker (`http://localhost:8080/`).

## Architecture (à lire avant toute modification)

Le détail est dans `docs/architecture/` : cas d'utilisation, puis une page par
module avec ses états, son API, ses tables et ses règles. S'y reporter plutôt
que de le déduire du code.

```
public/          racine web : index.html, css/, js/core/ (noyau), js/views/ (une vue par fichier)
api/             points d'entrée PHP, un fichier par ressource
src/Chess/       domaine pur : position, coups légaux, SAN, fin de partie, IA
src/Game/        parties : coups, pendule, ordinateur, nulle, PGN
src/Chat/        salons, messages privés, commentaires de partie
src/Tournament/  appariements, classements, cycle de vie
src/User/        comptes, connexion, invités, présence
src/Admin/       journal et tableau de bord
src/Shared/      ports communs : horloge, session, transactions, exceptions, accès
src/Infra/       implémentations : PDO, HTTP, session PHP, liens entre modules
sql/migrations/  migrations numérotées, seule source du schéma
tests/           Unit/, Integration/ (MySQL), http/ (contrôle HTTP), front/
```

Règles de dépendance, vérifiées par `tests/Unit/ArchitectureTest` :

- `Chess` ne dépend de rien.
- Un module (`Game`, `Chat`, `Tournament`, `User`, `Admin`) ne référence que
  `Chess`, `Shared` et lui-même. Il n'appelle jamais PDO, `$_SESSION`, `time()`
  ni `random_int()` : il passe par un port (`Clock`, `RandomSource`,
  `*Repository`…).
- `Infra` implémente les ports. Deux modules qui coopèrent le font par un
  adaptateur d'`Infra` (`Link/GameTournamentServices`), jamais directement.

Protocole d'API : `POST api/<ressource>.php` avec un corps JSON
`{ "action": …, … }` ; réponse `{ "ok": true, … }` ou
`{ "ok": false, "error": … }`. Une action = une méthode de service, appelée par
un contrôleur de `src/Infra/Http/Api/`. Les erreurs sont des exceptions de
`Shared` traduites en statut HTTP (422, 403, 404, 409, 429).

Schéma : une modification passe par un nouveau fichier `sql/migrations/NNN-nom.sql`
(règles d'écriture et plages de numéros en tête de `001-schema-initial.sql`).
Ne jamais modifier une migration déjà appliquée.

Le « temps réel » est un polling (2,5 s pour une partie, 3 s pour les salons et
messages, 5 à 8 s ailleurs, 25 s pour la présence) : conserver ces cadences.
Les relectures fréquentes envoient `known` (ce que la page affiche déjà) et
reçoivent `{ "unchanged": true }` si rien n'a bougé : garder ce chemin léger,
sans chargement de partie ni de messages.

## Conventions

- Langue : commentaires, commits et documentation en français ; identifiants en
  anglais.
- Commits : Conventional Commits (`feat`, `fix`, `docs`, `style`, `refactor`,
  `perf`, `test`, `build`, `ci`, `chore`, `revert`), en-tête de 100 caractères
  au plus, commits atomiques. La CI les contrôle avec commitlint.
- Branches : `feat/...`, `fix/...` — jamais de commit direct sur `main`. Chaque
  pull request fusionnée publie une version (étiquette `semver:major`,
  `semver:minor`, `semver:patch` ou `semver:none`).
- Sécurité : requêtes préparées partout ; aucun identifiant versionné
  (`api/config.php` et `.env` sont ignorés par git) ; pas de script en ligne
  ni d'`eval` dans le front, que la CSP de la page bloquerait (un nouveau
  script va dans `public/js/` et dans `index.html`) ; `esc()` sur toute valeur
  insérée dans la page.
- Moteur d'échecs (`src/Chess/`) : aucune modification sans tests de
  non-régression, perft compris.
- Toute modification de comportement ou d'API met à jour ses tests et la page
  correspondante de `docs/architecture/`. Une nouvelle action d'API a aussi son
  contrôle HTTP dans `tests/http/smoke.d/`.
- Modifications ciblées : pas de réécriture massive non demandée.

## Règles de travail

1. Lire la page de `docs/architecture/` du module concerné avant de le modifier.
2. Après chaque étape : tester, puis committer.
3. Poser des questions avant toute décision structurante non couverte ici.

## Commandes utiles

Sous WampServer, depuis la racine du dépôt (PHP 8.3 et Composer dans le PATH) :

```
composer test                 # tests unitaires PHPUnit
composer test:slow            # IA niveau maître
vendor/bin/phpstan analyse --memory-limit=1G
node tests/front/unit/run.mjs # calculs du front
php bin/migrate.php           # applique les migrations
```

- Les tests d'intégration (`composer test:integration`) vident leurs tables :
  ils visent la base `le_cercle_test` et lisent `DB_HOST`, `DB_PORT`, `DB_USER`
  et `DB_PASS` dans l'environnement. Sans ces variables, les lancer par Docker.
- Fins de ligne : `.gitattributes` impose LF pour tout le texte du dépôt,
  quel que soit `core.autocrlf` ; `composer lint` passe donc aussi sous Windows.
- Les commandes Docker et la CI sont décrites dans `README.md`.

## Reste à faire

- Connexion par un fournisseur externe (Google) : l'énumération
  `User\Provider` prévoit la valeur, aucun parcours OAuth n'existe encore.
