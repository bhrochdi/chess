# Utilisateurs (`src/User/`)

[Architecture](README.md) › Utilisateurs

Trois services, qui n'utilisent que `Shared` et leur propre dossier. Le dépôt
PDO est `src/Infra/User/PdoUserRepository.php`, le contrôleur
`src/Infra/Http/Api/AuthController.php`, branché par `api/auth.php`.

- `AuthService` : inscription, connexion, déconnexion, compte courant, comptes
  invités, prise du rôle d'administrateur par le premier compte.
- `UserAdminService` : liste des comptes et opérations d'administration.
- `PresenceService` : battement de présence et liste des joueurs en ligne.

Ports : `UserRepository` (implémenté par `PdoUserRepository`) et
`PasswordHasher` (`password_hash` / `password_verify`, dans `Infra`).
`UserFields` porte les règles des champs : pseudo de 2 à 16 caractères
(lettres, chiffres, `-`, `_`), e-mail valide, mot de passe de 6 à 72 octets
(bcrypt ignore les octets suivants).

La migration `400-presence.sql` ajoute `users.last_seen_at`.
`501-nettoyage-utilisateurs.sql` supprime `users.stats`,
`legacy_salt` et `legacy_hash`.

## API (`api/auth.php`)

Toutes les actions sont des `POST` JSON `{ "action": "…", … }` ; les erreurs
suivent le [tableau des contrats communs](infrastructure.md#contrats-communs-des-api).

| Action | Paramètres | Accès | Réponse |
|--------|------------|-------|---------|
| `register` | `pseudo`, `email`, `password` | visiteur | `{ user }` ; le premier compte inscrit devient administrateur |
| `login` | `identifiant` (pseudo ou e-mail), `password` | visiteur | `{ user }` ; 429 après trop d'échecs (voir plus bas) |
| `logout` | | tous | `{}` |
| `me` | | tous | `{ user }`, `null` sans session |
| `guest` | `pseudo?` | visiteur | `{ user }` : compte invité |
| `claimAdmin` | | compte inscrit | `{ user }` ; 409 si un administrateur existe |
| `list` | | admin | `{ users }` |
| `adminUpdate` | `targetId`, `op` | admin | `{}` |
| `purgeGuests` | | admin | `{ deleted }` : invités inactifs depuis 30 jours supprimés |
| `heartbeat` | | joueur | `{}` |
| `online` | | tous | `{ players }` : `{ id, pseudo }` vus depuis 70 s |

Règles :

- Une seule erreur de connexion : 403 « Identifiant ou mot de passe
  incorrect. », que le compte existe ou non. Un compte inconnu passe quand même
  par une vérification de hachage, pour que le temps de réponse ne révèle rien.
  La suspension n'est annoncée qu'après un mot de passe correct.
- L'identifiant de session est régénéré à la connexion ; la déconnexion détruit
  la session.
- `list` est réservé aux administrateurs, car il donne les e-mails. Le public
  n'a besoin que de `online`.
- Les conflits d'état d'`adminUpdate` répondent 409, les erreurs de saisie 422.
- Deux inscriptions simultanées sur le même pseudo ou le même e-mail
  répondent 409 ; un interblocage MySQL (1213) aussi, avec « Inscription
  simultanée, réessaie. ».

Un compte est `{ id, pseudo, email, provider, role, banned, stats, createdAt }`.
L'e-mail n'est visible que par son propriétaire et par les administrateurs ; les
autres reçoivent `null`. Aucune réponse ne contient de mot de passe ni de
hachage.

## Invités

Un invité est un vrai compte (`provider = 'guest'`) sans e-mail ni mot de passe.
Il sert d'identité serveur pour jouer, discuter et utiliser le jeu local.
Sans pseudo choisi, il s'appelle `Invite_` suivi de 8 caractères hexadécimaux.
Un invité ne peut pas devenir administrateur (`claimAdmin` 403, `promote` 409),
ne peut pas s'inscrire à un tournoi et ne peut plus se reconnecter après une
déconnexion. Les invités ne comptent pas pour la règle du premier compte
administrateur.

## Premier administrateur

`register` compte les comptes inscrits sous verrou (`FOR UPDATE`) dans la même
transaction que l'insertion : deux premières inscriptions simultanées ne peuvent
pas devenir administrateurs toutes les deux.

## Présence

Le front envoie `heartbeat` toutes les 25 s ; le serveur écrit
`users.last_seen_at`. `online` renvoie les comptes vus dans les 70 dernières
secondes, triés par pseudo.

## Administration

`adminUpdate` accepte `ban`, `unban`, `promote`, `demote` et `delete`. Chaque opération est inscrite au journal (`admin_log`) dans la même
transaction que la modification :

| Opération | Phrase du journal | Refus |
|-----------|-------------------|-------|
| `ban` | `Suspension de X` | 409 sur soi-même |
| `unban` | `Réactivation de X` | |
| `promote` | `Rôle de X → admin` | 409 pour un invité |
| `demote` | `Rôle de X → user` | 409 pour le dernier administrateur |
| `delete` | `Suppression du compte X` | 409 sur soi-même |

Une opération inconnue répond 422 « Opération inconnue. », un compte inconnu
404 « Utilisateur introuvable. ».

## Statistiques

Les victoires, défaites et nulles d'un compte se calculent à chaque lecture
depuis les parties en ligne terminées (port `GameStats`, une requête par
réponse) ; les parties locales et contre l'ordinateur ne comptent pas.

## Côté navigateur

« Promouvoir » n'apparaît pas pour un invité. Comme `list` est réservé aux
administrateurs, le front ne peut pas savoir s'il existe déjà un administrateur :
« Devenir administrateur » reste proposé aux comptes inscrits, et le 409 « Un
administrateur existe déjà. » le masque pour la session.

## Limitation des tentatives de connexion

Après 5 échecs sur un même compte en 15 minutes, ou 20 depuis une même
adresse, `login` répond 429 « Trop de tentatives de connexion. Réessaie dans
quelques minutes. », même avec le bon mot de passe, jusqu'à la fin de la
fenêtre. Le contrôle a lieu avant la vérification du mot de passe. Un compte
existant est compté par son id : passer du pseudo à l'e-mail ne remet pas le
compteur à zéro. Une connexion réussie efface les échecs du compte, pas ceux
de l'adresse. L'adresse est `REMOTE_ADDR` (aucun mandataire devant
l'application) ; `api/auth.php` la transmet à `AuthController`.

Port `LoginAttempts` (`failuresSince`, `record`, `clear`), implémenté par
`PdoLoginAttempts` sur la table `login_attempts` (migration
`502-tentatives-connexion.sql`). Les clés sont `id:` ou `ip:` suivi de
l'empreinte SHA-256 : ni identifiant ni adresse en clair. Les lignes de plus de
24 h sont effacées à chaque écriture.

## Purge des invités inactifs

`purgeGuests` (administrateurs) supprime les comptes invités sans activité
depuis 30 jours (`last_seen_at`, ou `created_at` s'ils n'ont jamais été vus) et
répond `{deleted}`. Journal : `Purge de N compte(s) invité(s) inactif(s)`, dans
la même transaction. Leurs parties restent, sous « (compte supprimé) » ; une
session qui désigne un compte supprimé est traitée comme déconnectée. Bouton
« Purger les invités inactifs » dans la carte Maintenance.

---

← [Discussions](discussions.md) · [Sommaire](README.md) · [Tournois](tournois.md) →
