# Le Cercle — plan de test manuel

Vérifie l'application servie (Docker ou WampServer) dans un vrai navigateur.

Les tests automatiques couvrent déjà une partie de ce plan : tests PHP
(`composer test`, `composer test:integration`), contrôle HTTP
(`tests/http/smoke.sh`) et test de fumée navigateur (`tests/front/smoke.sh` :
inscription, invité, partie contre l'ordinateur avec annulation, partie entre
deux sessions avec nulle, message privé, report automatique d'un résultat de
tournoi). Ce plan sert pour le reste et avant une livraison.

## 0. Mise en place

1. Installer l'application comme l'indique le `README.md` (Docker :
   `http://localhost:8080/` ; WampServer : hôte virtuel dont la racine est
   `public/`).
2. Partir d'une base neuve, puis créer les tables :
   ```
   php bin/migrate.php
   ```
   Attendu : une ligne « Appliquée : … » par migration ; un second appel répond
   « Base à jour. ».
3. Prévoir deux navigateurs distincts (ou un normal et une fenêtre privée) :
   deux sessions PHP indépendantes, appelées **Alice** et **Bob** ci-dessous.

**Critère transverse.** Dans l'onglet réseau (F12), chaque appel est un
`POST api/<ressource>.php` (`auth`, `games`, `rooms`, `direct-messages`,
`game-comments`, `tournaments`, `admin`). Un refus montre le message du serveur
tel quel. Aucune réponse 500.

---

## 1. Comptes

1. **Connexion / Créer un compte** → **Créer un compte** : créer le premier
   compte (Alice).
   - Attendu : connectée, rôle ADMIN, onglet **⚙ Admin** visible.
2. Recharger la page (F5).
   - Attendu : toujours connectée.
3. **Déconnexion**, puis F5.
   - Attendu : déconnectée.
4. Créer Bob dans le second navigateur.
   - Attendu : utilisateur normal, pas d'onglet **⚙ Admin**.
5. Recréer le pseudo `Alice` (casse quelconque).
   - Attendu : « Ce pseudo est déjà pris. »
6. Réutiliser l'e-mail d'Alice.
   - Attendu : « Un compte existe déjà avec cet e-mail. »
7. Se connecter avec un mauvais mot de passe.
   - Attendu : « Identifiant ou mot de passe incorrect. »
8. **Limitation des tentatives.** Cinq mauvais mots de passe sur le compte de
   Bob, puis le bon, par le pseudo ou par l'e-mail.
   - Attendu : « Trop de tentatives de connexion. Réessaie dans quelques
     minutes. » (HTTP 429), même avec le bon mot de passe.
   - Attendu : 15 minutes plus tard, la connexion réussit.
9. Fenêtre privée : **Continuer en invité** sans pseudo.
   - Attendu : connecté sous `Invite_` suivi de 8 caractères hexadécimaux.
   - Attendu : F5 garde la session.
10. Dans l'onglet réseau, la réponse de `me` ou de `login` ne contient aucune
    empreinte de mot de passe (`$2y$…`).

## 2. Jeu local et contre l'ordinateur

1. Déconnecté, ouvrir **Jeu local & IA**.
   - Attendu : position de départ ; le statut invite à se connecter ou à jouer
     en invité.
2. Connecté, choisir **Deux joueurs (même écran)** et **5 min**, puis
   **Nouvelle partie**.
   - Attendu : seuls les coups légaux sont proposés (pastilles) ; le roque, la
     prise en passant et la promotion (choix de la pièce) fonctionnent.
   - Attendu : la pendule du camp au trait décompte.
3. **↶ Annuler** après quelques coups.
   - Attendu : le dernier coup disparaît.
4. **⇅ Retourner**.
   - Attendu : l'échiquier et les coordonnées s'inversent.
5. Choisir **Ordinateur — club**.
   - Attendu : une nouvelle partie commence ; après chaque coup, « l'ordinateur
     réfléchit… », puis l'ordinateur répond.
   - Attendu : **↶ Annuler** retire le coup de l'ordinateur et le vôtre.
6. Recharger la page, puis rouvrir **Jeu local & IA**.
   - Attendu : la partie en cours reprend là où elle en était.
7. **⤓ Export PGN**.
   - Attendu : un fichier `.pgn` se télécharge, avec les coups en notation
     algébrique.
8. Partie en **3 min + 2 s** : jouer un coup, puis laisser tomber la pendule.
   - Attendu : à 0, la partie se termine au temps.

## 3. Partie en ligne

1. **Alice** : **Accueil** → **Créer et attendre un adversaire** (Blitz — 5 min).
   - Attendu : « En attente d'un adversaire… ».
2. **Bob** : la partie apparaît dans « Parties ouvertes — défis à relever » →
   **Rejoindre**.
   - Attendu : les deux écrans passent en partie en cours en quelques secondes
     (rafraîchissement toutes les 2,5 s).
3. Jouer quelques coups chacun son tour.
   - Attendu : chaque coup apparaît chez l'adversaire en 3 s au plus ; seul le
     joueur au trait peut jouer (« à toi ! »).
   - Attendu : les pendules des deux écrans concordent à une seconde près.
4. **Alice** : **½ Proposer nulle**.
   - Attendu chez Alice : « Proposition de nulle envoyée… ».
   - Attendu chez Bob : bouton **Accepter ½-½**.
   - Attendu après acceptation : « Partie nulle », résultat ½-½ des deux côtés.
5. Nouvelle partie, puis **⚐ Abandonner** (confirmation).
   - Attendu : la partie est perdue pour celui qui abandonne, chez les deux
     joueurs.
6. Un troisième compte : **Accueil** → « Parties en cours — à regarder » →
   **👁 Regarder**.
   - Attendu : l'échiquier suit la partie ; « mode spectateur » ; aucun coup
     possible.
7. **Commentaires en direct** : chacun écrit un message sous la partie.
   - Attendu : les autres le voient en 3 s au plus.
8. Partie en 3 min + 2 s : laisser tomber la pendule d'un joueur.
   - Attendu : fin au temps, des deux côtés, sans action de l'adversaire.
9. Après quelques parties terminées, ouvrir **Mon profil**.
   - Attendu : victoires, défaites et nulles comptent les parties en ligne
     terminées seulement (pas les parties locales ni contre l'ordinateur).

## 4. Salons

1. **Alice** et **Bob** : onglet **Salon**, salon « Général ».
   - Attendu : un message d'Alice apparaît chez Bob en 3 s au plus.
2. Bob sur l'**Accueil** pendant qu'Alice écrit.
   - Attendu : un badge de non-lus apparaît sur l'onglet **Salon**.
3. Changer de salon dans le sélecteur.
   - Attendu : le titre, la description et le fil suivent le salon choisi ; les
     messages d'un salon n'apparaissent pas dans un autre.
4. Un message vide ou trop long est refusé (« Message vide. » ou « Message trop
   long (… caractères maximum). »).

## 5. Messages privés

1. **Alice** : **Messages** → saisir `Bob` → **＋ Créer**, écrire un message.
   - Variante : depuis l'**Accueil**, icône ✉️ en face de Bob dans « Joueurs en
     ligne ».
2. **Bob** : **Messages**.
   - Attendu : la conversation avec Alice, avec un badge de non-lus ; l'ouvrir
     fait disparaître le badge.
3. Bob répond.
   - Attendu : Alice voit la réponse en 3 s au plus.
4. Un troisième compte ne voit pas la conversation Alice–Bob.
5. S'écrire à soi-même.
   - Attendu : « Tu ne peux pas t’écrire à toi-même. »
6. Bob suspendu par l'administration (section 7).
   - Attendu : Bob ne peut plus ni lire ni envoyer de messages privés.

## 6. Tournois

### 6.1 Cycle de vie (Alice, administratrice)

1. **⚙ Admin** → carte **Tournois** : créer « Open du Cercle » (round-robin,
   Blitz — 5 min).
   - Attendu : statut « Inscriptions ouvertes ».
   - Attendu : recréer le même nom donne « Ce nom de tournoi est déjà pris. »
2. **Gérer** → **Lancer le tournoi** avec un seul inscrit.
   - Attendu : « Au moins 2 joueurs inscrits sont nécessaires. »
3. Faire inscrire au moins deux joueurs (6.2), puis **Lancer le tournoi**.
   - Attendu : statut en cours, ronde 1 appariée, une partie en ligne créée par
     match (visible dans la carte **Parties**).
   - Attendu : lancer un second tournoi donne « Un autre tournoi est déjà en
     cours (un seul à la fois). »
4. Saisir un résultat à la main (`1-0`, `½`, `0-1`, `✕` pour effacer).
   - Attendu : le classement se met à jour (1, ½, 0 ; tri par points puis par
     victoires).
5. **Ronde suivante** avec des résultats manquants.
   - Attendu : « Complète tous les résultats de la ronde avant de générer la
     suivante. »
6. Modifier un résultat d'une ronde passée.
   - Attendu : « Seuls les résultats de la ronde en cours peuvent être
     modifiés. »
7. **Clore le tournoi**.
   - Attendu : statut terminé, classement figé.
8. Le journal d'administration (section 7) trace chaque action.

### 6.2 Joueurs

1. **Alice** et **Bob** : **Tournois** → ouvrir le tournoi → s'inscrire.
   - Attendu : le nombre d'inscrits augmente ; **Me désinscrire** fonctionne
     tant que les inscriptions sont ouvertes.
2. Un invité tente de s'inscrire.
   - Attendu : « Un compte invité ne peut pas s'inscrire à un tournoi. »
3. Après le lancement, chaque joueur voit **♟ Jouer ma partie (ronde N)**.
   - Attendu : le bouton ouvre la partie en ligne.
   - Attendu : la pendule ne démarre qu'au premier coup des Blancs.
4. Terminer la partie (mat, abandon, nulle ou temps).
   - Attendu : le résultat apparaît dans la grille et le classement en 6 s au
     plus, sans action de l'administration.
5. Un non-participant peut **👁 Suivre** une partie du tournoi.

### 6.3 Élimination directe

1. Format « Élimination directe », 5 inscrits, lancer.
   - Attendu : tableau de 8, soit 1 match réel et 3 exempts. Un exempt compte
     comme une victoire et n'a pas de partie.
2. Un match nul bloque **Ronde suivante** (« Élimination : un match est nul.
   Saisis un résultat décisif (départage) avant de continuer. »).
3. Jusqu'à la finale.
   - Attendu : ensuite, « Il ne reste qu’un joueur : clôture le tournoi. »

### 6.4 Système suisse

1. Format « Système suisse », 3 rondes, 5 inscrits, lancer.
   - Attendu : le joueur sans adversaire est exempt (1 point).
2. Rondes suivantes.
   - Attendu : appariement par score, sans rejouer une paire.
   - Attendu : après la 3ᵉ ronde, « Nombre de rondes du suisse atteint : clôture
     le tournoi. »

## 7. Administration (Alice)

1. **Tableau de bord**.
   - Attendu : les compteurs (comptes, en ligne, défis ouverts, parties en
     cours, terminées, messages de salons) correspondent à ce qui a été fait.
2. **Utilisateurs** : **Suspendre** Bob, puis **Réactiver**.
   - Attendu : Bob suspendu ne peut plus se connecter (« Compte suspendu par
     l’administration. »).
3. **Promouvoir** Bob, puis **Rétrograder**.
   - Attendu : rétrograder le dernier administrateur donne « Impossible de
     rétrograder le dernier administrateur. »
   - Attendu : **Promouvoir** n'apparaît pas pour un invité.
4. **Supprimer** un compte.
   - Attendu : ses parties restent, sous « (compte supprimé) ».
5. **Parties** : **Voir** ouvre une partie en ligne ; **Clore** termine une
   partie en cours (décision administrative, nulle) ; **Suppr.** l'efface avec
   ses coups et ses commentaires.
6. **Salons** :
   - Créer « Débutants » ; recréer « débutants » donne « Ce nom de salon est
     déjà pris. »
   - **Renommer**, réordonner (↑ ↓), archiver.
   - Attendu : un salon archivé disparaît du sélecteur de la vue **Salon**, mais
     reste dans la modération.
   - « Général » n'a ni archivage ni suppression.
   - **Supprimer** un autre salon efface aussi ses messages.
7. **Modération des salons** : choisir un salon, **✕** sur un message, puis
   **Vider**.
   - Attendu : seul le salon choisi est touché.
8. **Maintenance** :
   - **Purger les parties terminées** : les parties terminées disparaissent de la
     carte **Parties**.
   - **Purger les invités inactifs** : supprime les comptes invités sans activité
     depuis 30 jours et affiche leur nombre.
9. **Journal d'administration** : chaque action ci-dessus y figure, avec son
   auteur et sa date.
10. Bob (inscrit, non administrateur) : **⚙ Admin** → **Devenir
    administrateur**.
    - Attendu : « Un administrateur existe déjà. », puis le bouton disparaît
      pour la session. Il n'accorde le rôle que s'il n'existe aucun
      administrateur.
    - Attendu : un invité n'a pas ce bouton.

## 8. Contrôles hors navigateur

Avec `curl` sur l'application servie (`http://localhost:8080` sous Docker) :

| Cas | Requête | Attendu |
|-----|---------|---------|
| Méthode | `GET /api/games.php` | 405 |
| Action inconnue | `POST /api/games.php` `{"action":"boom"}` | 400 « Action inconnue. » |
| Sans session | `POST /api/admin.php` `{"action":"journal"}` | 403 « Connexion requise. » |
| Non administrateur | `adminUpdate` avec la session de Bob | 403 « Action réservée aux administrateurs. » |
| Configuration | `GET /api/config.php` | 404 |
| Hors racine web | `GET /src/…`, `/vendor/autoload.php`, `/sql/…`, `/composer.json` | 404 |
| Ancien stockage | `GET /api/storage.php` | 404 |

`tests/http/smoke.sh <url>` rejoue ces contrôles et d'autres.

## 9. Vérifications en base

```
SELECT name FROM schema_migrations ORDER BY name;
SHOW TABLES;
SELECT pseudo, provider, role, LEFT(pass_hash, 4) AS empreinte, banned FROM users;
SELECT attempt_key, COUNT(*) FROM login_attempts GROUP BY attempt_key;
SELECT actor_id, action FROM admin_log ORDER BY id DESC LIMIT 10;
```

- Attendu : ni `kv_store` ni `stat_records` ; `users` n'a plus de colonnes
  `stats`, `legacy_salt` ou `legacy_hash`.
- Attendu : `pass_hash` commence par `$2y$` ; un invité n'en a pas.
- Attendu : `login_attempts` ne contient que des empreintes (`id:` ou `ip:` suivi
  de 64 caractères hexadécimaux), jamais un pseudo ni une adresse en clair.
