# Sécurité et tests

[Architecture](README.md) › Sécurité et tests

## Sécurité

| Menace | Réponse |
|--------|---------|
| Joueur qui modifie le JavaScript de sa page | aucune règle côté navigateur ; chaque action est revérifiée par le service (trait, légalité, statut, droits) |
| Requêtes concurrentes ou en retard | verrou de ligne (`FOR UPDATE`) dans une transaction par écriture ; numéro de demi-coup attendu (`ply`) sur chaque coup |
| Vol de mot de passe en base | `password_hash` (bcrypt) ; aucune empreinte dans une réponse |
| Essais de mots de passe en série | 5 échecs par compte ou 20 par adresse en 15 minutes, puis 429 ; clés hachées en base |
| Création de comptes en masse | 30 comptes invités et 30 inscriptions par adresse et par heure, puis 429 |
| Inondation des salons, des messages privés ou des commentaires | 8 messages par compte en 20 secondes, tous canaux confondus, puis 429 |
| Inondation de la liste des défis ou de la base | 5 défis en ligne et 30 parties locales par compte en 10 minutes, puis 429 |
| Découverte des comptes existants | même message et même temps de calcul pour un compte inconnu et un mauvais mot de passe |
| Fixation de session | identifiant de session régénéré à la connexion, session détruite à la déconnexion |
| Injection SQL | requêtes préparées partout (PDO) |
| Injection HTML dans les messages | `esc()` sur toute valeur insérée dans la page ; en seconde ligne, la politique de sécurité du contenu (CSP) de la page n'exécute que les scripts du site (`script-src 'self'`, aucun script en ligne, pas d'`eval`) |
| Page affichée dans un cadre d'un autre site (clic détourné) | `frame-ancestors 'none'` et `X-Frame-Options: DENY` |
| Vol du cookie de session | cookie `HttpOnly` et `SameSite=Lax` ; `Secure` dès que le site est servi en HTTPS (`Infrastructure::isHttps()`) |
| Réponse interprétée comme un autre type de fichier | `X-Content-Type-Options: nosniff` sur la page et sur chaque réponse de l'API |
| Fichiers sensibles servis par Apache | racine web limitée à `public/` ; `api/config*.php` répond 404 ; identifiants hors du dépôt (`api/config.php`, `.env`) |
| Abus d'un administrateur | journal écrit dans la même transaction que chaque action d'administration |

En-têtes de sécurité : ceux de la page sont posés par Apache (lignes `Header`
de `docker/php/apache-site.conf` et de l'hôte virtuel WampServer du README),
ceux de l'API par PHP (`JsonResponse::headers()`), quelle que soit la
configuration du serveur. `tests/http/smoke.sh` vérifie les deux. Le test de
fumée navigateur tourne avec la vraie CSP : il attend ses conditions par
`page.evaluate` (fonction `until`), car `waitForFunction` de Playwright
évalue une chaîne, ce que la CSP interdit.

## Tests et qualité

| Niveau | Où | Ce qui est vérifié |
|--------|----|--------------------|
| Unitaires | `tests/Unit/` | domaine échecs (perft compris), services avec doublures en mémoire, contrats JSON des contrôleurs, règles de dépendance (`ArchitectureTest`) |
| Lents | groupe `slow` | IA niveau maître dans son budget de temps |
| Intégration | `tests/Integration/` | dépôts PDO et migrations contre un vrai MySQL (base `le_cercle_test`) |
| HTTP | `tests/http/smoke.sh` | application servie : fichiers masqués, statuts et messages de chaque ressource |
| Navigateur | `tests/front/` | calculs du front (Node), cohérence des scripts, test de fumée Playwright de bout en bout |
| Manuel | [TESTS-MANUELS.md](../TESTS-MANUELS.md) | ce que les tests automatiques ne couvrent pas |

Le style suit PER-CS 2.0 (PHP-CS-Fixer) et PHPStan contrôle le niveau 8. La CI
GitHub Actions lance quatre tâches (`lint`, `test`, `front`, `integration`) sous
forme de cibles du `docker/php/Dockerfile` et de la pile `docker compose`.

---

← [Administration](administration.md) · [Sommaire](README.md)
