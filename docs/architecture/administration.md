# Administration (`src/Admin/`)

[Architecture](README.md) › Administration

`AdminService` donne le journal et le tableau de bord, en lecture seule et
réservés aux administrateurs (`api/admin.php`) :

| Action | Réponse |
|--------|---------|
| `journal` | `{ entries }` : 200 entrées `{ actor: { id, pseudo }, action, at }`, les plus récentes d'abord |
| `dashboard` | `{ counts }` : `accounts`, `online` (vus depuis 70 s), `open`, `playing`, `finished` (parties en ligne), `roomMessages` |

Ports : `JournalReader` et `DashboardCounts`, implémentés dans
`src/Infra/Admin/`. Les écritures d'administration restent dans leur module
(`Game`, `Chat`, `Tournament`, `User`), qui journalise lui-même. La
maintenance purge les parties terminées et les invités inactifs ; une
sauvegarde se fait avec `mysqldump`.

---

← [Tournois](tournois.md) · [Sommaire](README.md) · [Sécurité et tests](securite-et-tests.md) →
