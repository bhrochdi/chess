# Le Cercle — Plan de test manuel (backend PHP/MySQL)

Vérifie que la plateforme fonctionne de façon autonome sur WampServer,
sans l'API `window.storage` de l'artefact Claude.ai.

## 0. Mise en place (une seule fois)

1. **Base de données** — créer la base et la table :
   ```
   c:/wamp64/bin/mysql/mysql8.0.27/bin/mysql.exe -u root -p < sql/schema.sql
   ```
2. **Configuration** — copier l'exemple et renseigner les identifiants :
   ```
   copy api\config.example.php api\config.php
   ```
   puis éditer `api/config.php` (`db_pass`, etc.). Ce fichier est ignoré par git.
3. **Servir l'application** — vérifier que WampServer (Apache) tourne, puis ouvrir :
   ```
   http://localhost/LeCercle/
   ```
   (adapter le chemin selon l'emplacement réel sous `c:/wamp64/www/`).

**Critère transverse** : la bannière orange « stockage partagé indisponible »
ne doit **jamais** apparaître. Si elle s'affiche, l'app est ouverte en `file://`
ou Apache/MySQL ne répond pas → l'onglet réseau du navigateur doit montrer des
appels `POST api/storage.php` en `200`.

> Astuce diagnostic : ouvrir la console réseau (F12) et filtrer sur
> `storage.php`. Toute réponse `≠ 200` (404 = mauvais chemin, 500 = config/DB)
> pointe la cause.

---

## 1. Compte (authentification serveur via la base kv_store)

1. Cliquer **Connexion / Créer un compte** → onglet **Créer un compte**.
2. Créer le **premier** compte (ex. `Alice` / e-mail / mot de passe).
   - ✅ Attendu : connexion immédiate, et **rôle ADMIN** affiché (1er compte).
   - ✅ Le bouton **⚙ Admin** apparaît dans la navigation.
3. Se déconnecter, recharger la page (F5).
   - ✅ Attendu : reconnexion **automatique** n'a pas lieu après déconnexion,
     mais après un simple rechargement *sans* déconnexion la session est
     conservée (clé `session`, scope personnel).
4. Créer un **second** compte `Bob` (autre navigateur ou fenêtre privée).
   - ✅ Attendu : `Bob` est un utilisateur normal (pas admin).
   - ✅ Tester l'unicité : recréer `Alice` → message « pseudo déjà pris ».
5. Vérifier la persistance en base :
   ```
   SELECT cle FROM le_cercle.kv_store WHERE scope='' AND cle='db:users';
   ```
   - ✅ Attendu : une ligne `db:users` contenant les deux comptes.

---

## 2. Partie en ligne entre deux navigateurs

Utiliser **deux navigateurs distincts** (ou un normal + un en navigation privée)
pour avoir deux sessions PHP indépendantes. Navigateur 1 = `Alice`, navigateur 2 = `Bob`.

1. **Alice** : Accueil → **Créer et attendre un adversaire** (cadence 5 min).
   - ✅ La partie s'ouvre, statut « En attente d'un adversaire… ».
2. **Bob** : Accueil → la partie d'Alice apparaît dans **Parties ouvertes** →
   **Rejoindre**.
   - ✅ Les deux écrans passent en « partie en cours » sous quelques secondes
     (polling 2,5 s).
3. Jouer **3-4 coups** en alternance.
   - ✅ Chaque coup apparaît chez l'adversaire en ≤ 3 s, pendules décomptées.
   - ✅ Les coups illégaux sont refusés ; le roque / la prise en passant
     fonctionnent (non-régression moteur).
4. **Alice** : **Proposer nulle** → **Bob** voit la proposition → **Accepter**.
   - ✅ Les deux écrans affichent « Partie nulle » (½-½).
   - *(Variante : tester **Abandonner** sur une autre partie.)*
5. Après la fin, recharger l'Accueil des deux côtés.
   - ✅ Statistiques V/D/N mises à jour pour les comptes (clé `db:users`).
   - ✅ La partie bascule de « en cours » à « terminée ».
6. **Spectateur** : un 3e visiteur (ou Alice depuis l'Accueil) clique
   **👁 Regarder** sur une partie en cours.
   - ✅ Le plateau se met à jour sans pouvoir jouer.

---

## 3. Salon (chat partagé)

> Depuis `feat/admin-salons`, le salon n'est plus unique : voir la **section 7**
> pour les salons multiples. Les étapes ci-dessous valent pour le salon actif
> (« Général » par défaut) ; la clé en base est désormais `chat:room:general`.

1. **Alice** : onglet **Salon**, envoyer un message.
2. **Bob** : onglet **Salon**.
   - ✅ Le message d'Alice apparaît en ≤ 3 s.
   - ✅ Le compteur « N en ligne » reflète les visiteurs actifs.
3. **Bob** sur un autre onglet (Accueil) pendant qu'Alice écrit.
   - ✅ Un **badge** rouge apparaît sur l'onglet Salon (messages non lus).
4. Vérification base :
   ```
   SELECT JSON_LENGTH(valeur) FROM le_cercle.kv_store WHERE cle='chat:room:general' AND scope='';
   ```
   - ✅ Le nombre de messages augmente.

---

## 4. Messagerie privée

1. **Alice** : onglet **Messages** → saisir `Bob` → **＋**, écrire un message.
   - *(ou depuis l'Accueil : icône ✉️ en face de Bob dans « Joueurs en ligne ».)*
2. **Bob** : onglet **Messages**.
   - ✅ La conversation avec `Alice` apparaît, avec **badge** de non-lus.
   - ✅ En ouvrant le fil, les messages s'affichent et le badge disparaît.
3. **Bob** répond → **Alice** voit la réponse en ≤ 3 s.
4. ✅ Isolation : un **3e compte** `Chloé` ne voit **pas** le fil Alice↔Bob
   dans sa liste de conversations.
5. Vérification base (la clé du fil est triée alphabétiquement) :
   ```
   SELECT cle FROM le_cercle.kv_store WHERE cle LIKE 'dm:%' AND scope='';
   ```
   - ✅ Attendu : `dm:Alice--Bob`.

> Note : `dmread` (suivi des messages lus) est **personnel** → stocké sous
> le scope de session, invisible des autres. Vérifier :
> ```
> SELECT COUNT(*) FROM le_cercle.kv_store WHERE cle='dmread' AND scope<>'';
> ```

---

## 5. Administration (compte `Alice`, rôle admin)

1. Onglet **⚙ Admin** → **Tableau de bord**.
   - ✅ Les compteurs (comptes, en ligne, défis, parties, msgs salon) sont cohérents.
2. **Utilisateurs** : sur `Bob`, tester **Suspendre** puis **Réactiver**.
   - ✅ `Bob` suspendu ne peut plus se connecter (message « compte suspendu »).
   - ✅ Le **journal d'administration** enregistre chaque action.
3. **Promouvoir** `Bob` admin puis **Rétrograder**.
   - ✅ Impossible de rétrograder le **dernier** admin (garde-fou).
4. **Parties** : **Clore** une partie en cours, **Supprimer** une partie.
   - ✅ La partie disparaît / passe en terminée ; `game:<id>` et `gamechat:<id>`
     sont supprimés en base.
5. **Modération des salons** : choisir un salon dans le sélecteur, supprimer un
   message, puis **Vider** (cf. aussi la section 7 pour la gestion des salons).
6. **Maintenance** :
   - **Sauvegarde complète (JSON)** → un fichier se télécharge (contient `salons`
     + `salons_messages`, format v2).
   - **Purger les parties terminées** → les lignes `game:*` terminées disparaissent.
   - **Réinitialiser** (double confirmation) → toutes les clés partagées sont vidées.
7. Vérification base après réinitialisation :
   ```
   SELECT cle FROM le_cercle.kv_store WHERE scope='';
   ```
   - ✅ `db:users` vide, `gamesindex` vide ; `db:rooms` contient de nouveau le
     seul salon « Général » et `chat:room:general` est vide.

---

## 6. Robustesse de l'endpoint (optionnel, hors navigateur)

Avec WampServer actif (`http://localhost/LeCercle/api/storage.php`) :

| Cas | Requête | Attendu |
|-----|---------|---------|
| Méthode GET | `GET api/storage.php` | HTTP **405** |
| Action inconnue | `{"action":"boom"}` | HTTP **400** |
| Clé invalide | `key` avec espace / `/` / guillemet | HTTP **400** « Clé invalide » |
| Valeur non-JSON | `set` avec `value` non JSON | HTTP **400** |
| Config manquante | renommer `config.php` | HTTP **500** explicite |

*(Ces cas ont déjà été validés via le serveur intégré PHP lors du développement ;
les rejouer confirme la configuration Apache.)*

---

## 7. Salons thématiques multiples (`feat/admin-salons`)

Remplace le salon unique par plusieurs salons administrables. La couche de
données (`db:rooms` + `chat:room:<id>`) a été validée par test unitaire ;
ces scénarios couvrent l'usage réel en navigateur.

### 7.1 Migration (au premier chargement après mise à jour)
1. Si la plateforme contenait déjà des messages de salon, recharger l'app une fois.
2. Vérification base :
   ```
   SELECT cle FROM le_cercle.kv_store WHERE scope='' AND (cle='db:rooms' OR cle LIKE 'chat:room:%' OR cle='chat:global');
   ```
   - ✅ `db:rooms` existe (contient « Général », id `general`).
   - ✅ `chat:room:general` reprend les anciens messages.
   - ✅ `chat:global` a **disparu** (migré puis supprimé).

### 7.2 Administration — gestion des salons (compte admin)
1. Onglet **⚙ Admin** → carte **Salons**.
   - ✅ « Général » est présent, marqué **DÉFAUT**, sans bouton Archiver/Supprimer.
2. **Créer** : nom « Débutants » + description → **Créer le salon**.
   - ✅ Apparaît dans la table ; `db:rooms` gagne une entrée (id `debutants`).
   - ✅ Recréer « débutants » (casse différente) → message « nom déjà pris ».
   - ✅ Nom < 2 caractères → message d'erreur, pas de création.
3. **Renommer** « Débutants » → « Débutants & Conseils ».
   - ✅ Nom mis à jour ; l'id `debutants` reste inchangé (clé stable).
4. **Réordonner** avec ↑/↓ → l'ordre change dans la table **et** dans le
   sélecteur côté vue Salon.
5. **Archiver** « Débutants & Conseils ».
   - ✅ Marqué **ARCHIVÉ** ; il **disparaît** du sélecteur de la vue Salon
     (utilisateur) mais reste visible dans la modération (admin).
6. **Supprimer** un salon (autre que Général) → confirmation, puis
   ✅ disparaît de `db:rooms` et `chat:room:<id>` est supprimé en base.
7. ✅ Chaque action (création, renommage, archivage, suppression) est tracée
   dans le **journal d'administration**.

### 7.3 Utilisateur — vue Salon multi-salons (deux navigateurs)
1. **Alice** (vue **Salon**) : sélectionner « Général », envoyer un message ;
   sélectionner un autre salon, envoyer un autre message.
   - ✅ Le titre/description et le fil suivent le salon choisi.
   - ✅ Isolation : les messages d'un salon n'apparaissent pas dans l'autre
     (`chat:room:general` ≠ `chat:room:<autre>` en base).
2. **Bob** sur le **même** salon que le dernier message d'Alice.
   - ✅ Le message apparaît en ≤ 3 s (polling 3 s conservé).
3. **Bob** sur l'onglet **Accueil**, Alice écrit dans le salon **actif** de Bob.
   - ✅ Un **badge** apparaît sur l'onglet Salon. Changer de salon remet le
     compteur à zéro (pas de faux badge).
4. Côté admin, **archiver** le salon actif d'Alice pendant qu'elle y est :
   - ✅ Au rafraîchissement, son sélecteur bascule automatiquement sur « Général ».

### 7.4 Modération par salon (admin)
1. Carte **Modération des salons** → choisir un salon dans le sélecteur.
   - ✅ La liste affiche les messages **de ce salon** uniquement.
2. **✕** sur un message → supprimé de ce salon seulement.
3. **Vider** → confirme avec le nom du salon, vide `chat:room:<id>` ciblé.
   - ✅ Le compteur « Msgs salons » du tableau de bord (somme de tous les
     salons) diminue en conséquence.
