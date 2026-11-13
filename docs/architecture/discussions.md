# Discussions (`src/Chat/`)

[Architecture](README.md) › Discussions

Trois services, tous dans `src/Chat/`, qui n'utilisent que `Shared` et leur
propre dossier. Les dépôts PDO sont dans `src/Infra/Chat/`, les contrôleurs dans
`src/Infra/Http/Api/`.

- `RoomService` possède les salons : lecture et envoi pour les joueurs, création,
  renommage, ordre, archivage, suppression et modération pour les
  administrateurs.
- `DirectMessageService` possède les messages privés entre deux joueurs et leurs
  compteurs de non-lus.
- `GameCommentService` possède les commentaires en direct sous une partie.

| Classe | Rôle |
|--------|------|
| `Room`, `ChatMessage`, `ThreadSummary` | Objets de valeur : un salon, un message (salon, privé ou commentaire), le résumé d'une conversation. |
| `RoomRepository`, `RoomMessageRepository`, `DirectMessageRepository`, `GameCommentRepository` | Ports implémentés par `Infra\Chat\Pdo*Repository`. |
| `RoomFields`, `MessageText` | Règles des champs : nom de salon 2 à 40 caractères, description 200 au plus, message 1 à 500 caractères après `trim`. |
| `MessageViews` | Représentations JSON des messages et des salons. Les auteurs et les créateurs se résolvent avec un seul `Members::findMany()` par réponse. |

## Tables

| Table | Contenu |
|-------|---------|
| `rooms` | Salons. Le nom est unique (`uniq_rooms_name`, collation `utf8mb4_unicode_ci`, donc insensible à la casse et aux accents). `general` est créé par la migration 200. |
| `room_messages` | Messages d'un salon. Clé étrangère vers `rooms` avec `ON DELETE CASCADE` : supprimer un salon supprime ses messages. |
| `direct_messages` | Messages privés, un par ligne, avec deux index pour lire une conversation dans les deux sens. |
| `dm_reads` | Dernier message lu par conversation `(user_id, peer_id)`. Le compteur de non-lus est le nombre de messages reçus dont l'identifiant dépasse cette valeur. |
| `game_comments` | Commentaires d'une partie. Pas de clé étrangère vers `games` (les deux modules restent indépendants) : le service vérifie l'existence par le port `CommentableGames`, et `PdoGameRepository::delete()` efface les commentaires. |

Migrations : `200-salons.sql`, `201-messages-prives.sql`,
`202-commentaires-parties.sql`. Les messages ne sont jamais tronqués en base ;
seuls les derniers sont renvoyés (80 pour un salon ou une partie, 100 pour une
conversation privée).

## API

Toutes les actions sont des `POST` JSON `{ "action": "…", … }`. Les erreurs
suivent le [tableau des contrats communs](infrastructure.md#contrats-communs-des-api). Les lectures de `rooms.php` et de
`game-comments.php` sont ouvertes aux visiteurs anonymes ; `direct-messages.php`
exige un compte non suspendu pour toutes ses actions.

`api/rooms.php`

| Action | Paramètres | Accès | Réponse |
|--------|------------|-------|---------|
| `list` | | tous | `{ rooms }` : salons non archivés |
| `listAll` | | admin | `{ rooms }` : tous les salons |
| `messages` | `roomId` | tous ; un salon archivé n'est lisible que par un administrateur (sinon 404) | `{ messages }` : les 80 derniers |
| `send` | `roomId`, `text` | joueur ; 409 si le salon est archivé | `{ message }` |
| `create` | `name`, `description?` | admin | `{ room }` ; 409 si le nom est pris |
| `rename` | `roomId`, `name` | admin | `{ room }` ; 409 si le nom est pris |
| `archive` | `roomId`, `archived` | admin | `{ room }` ; 409 si l'on archive « Général » |
| `move` | `roomId`, `direction` (`up` ou `down`) | admin | `{ rooms }` : tous, dans le nouvel ordre |
| `delete` | `roomId` | admin | `{}` ; 409 pour « Général » |
| `deleteMessage` | `roomId`, `messageId` | admin | `{}` |
| `clear` | `roomId` | admin | `{}` : vide le salon |

Un salon est `{ id, name, description, position, archived, isDefault, createdBy,
createdAt }`, avec `createdBy` à `null` pour « Général ». Un message est
`{ id, author, text, createdAt }`.

`api/direct-messages.php`

| Action | Paramètres | Réponse |
|--------|------------|---------|
| `open` | `pseudo` | `{ peer }` : le joueur à qui écrire ; 404 s'il n'existe pas |
| `threads` | | `{ threads, unreadTotal }` : les 12 conversations les plus récentes |
| `conversation` | `peerId` | `{ messages }` : les 100 derniers ; marque la conversation comme lue |
| `send` | `peerId`, `text` | `{ message }` |

Une conversation est `{ peer, last: { text, createdAt, fromMe }, unread }`.
`unreadTotal` compte les messages non lus de toutes les conversations, pas
seulement des 12 renvoyées. Écrire à soi-même est refusé (422). Un interlocuteur
supprimé reste listé comme « (compte supprimé) » mais sa conversation répond 404.

`api/game-comments.php`

| Action | Paramètres | Accès | Réponse |
|--------|------------|-------|---------|
| `list` | `gameId` | tous | `{ comments }` : les 80 derniers |
| `send` | `gameId`, `text` | joueur | `{ comment }` |

Seules les parties en ligne existantes acceptent des commentaires ; toute autre partie, ou un identifiant inconnu, répond 404 « Partie introuvable. ».

## Règles

- « Général » (`general`) ne peut être ni archivé (409 « Le salon « Général »
  reste toujours visible. ») ni supprimé (409 « Le salon « Général » ne peut pas
  être supprimé. »).
- Les noms de salon sont uniques sans tenir compte de la casse (409 « Ce nom de
  salon est déjà pris. »). Avec MySQL, la comparaison du service utilise la
  collation de la colonne et ignore aussi les accents (« General » est refusé si
  « Général » existe). Un doublon qui échappe au contrôle du service, par exemple
  deux créations simultanées, est refusé par l'index unique et le dépôt le
  traduit en la même réponse 409. Renommer un salon avec son propre nom ne fait
  rien et n'écrit pas au journal.
- Archiver un salon déjà archivé, ou désarchiver un salon actif, ne change rien
  et n'écrit pas au journal. Archiver « Général » reste refusé (409).
- `move` renumérote tous les salons dans l'ordre de la liste, ce qui sépare deux
  salons qui auraient la même position. Un salon inconnu répond 404 « Salon
  introuvable. ».
- Un salon archivé disparaît de `list`, refuse les envois et ne se lit plus que
  dans l'administration.
- Les identifiants de salon sont générés (`r` + 12 caractères hexadécimaux), pas
  tirés du nom.
- Chaque écriture d'administration est inscrite au journal (`admin_log`) dans la
  même transaction que la modification :

  | Action | Phrase du journal |
  |--------|-------------------|
  | `create` | `Création du salon « X »` |
  | `rename` | `Salon « X » renommé « Y »` |
  | `move` | `Salon « X » déplacé` (rien n'est écrit si le salon est déjà en bout de liste) |
  | `archive` | `Archivage du salon « X »` ou `Désarchivage du salon « X »` |
  | `delete` | `Suppression du salon « X »` |
  | `deleteMessage` | `Suppression d’un message du salon « X » (pseudo)` |
  | `clear` | `Salon « X » vidé` |

---

← [Parties](parties.md) · [Sommaire](README.md) · [Utilisateurs](utilisateurs.md) →
