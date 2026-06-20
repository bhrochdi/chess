<?php
declare(strict_types=1);

/*
 * Le Cercle — authentification côté serveur.
 *
 * POST JSON, actions :
 *   register   { pseudo, email, password }      -> crée + connecte
 *   login      { identifiant, password }        -> connecte (pseudo ou e-mail)
 *   logout     {}
 *   me         {}                               -> compte de la session, ou null
 *   list       {}                               -> comptes (champs publics)
 *   claimAdmin {}                               -> devient admin si aucun n'existe
 *   adminUpdate{ targetId, op }                 -> ban/unban/promote/demote/delete/resetstats
 *   recordResult { gameId, outcome }            -> stats du compte de la session
 *   ssoLogin   { provider, email, pseudo }      -> connexion démo SSO (sans mot de passe)
 *
 * Le hachage (password_hash/password_verify) et toute la vérification se font
 * ICI ; le hash ne quitte jamais le serveur. La session est une session PHP.
 */

header('Content-Type: application/json; charset=utf-8');
session_start();

function rep(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    rep(405, ['ok' => false, 'error' => 'Méthode non autorisée : POST requis.']);
}
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    rep(500, ['ok' => false, 'error' => 'Configuration absente : copier config.example.php vers config.php.']);
}
$config = require $configFile;
$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) {
    rep(400, ['ok' => false, 'error' => 'Corps JSON invalide.']);
}
$action = (string) ($req['action'] ?? '');

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db_host'], (int) ($config['db_port'] ?? 3306), $config['db_name'], $config['db_charset'] ?? 'utf8mb4');
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('[le-cercle/auth] connexion : ' . $e->getMessage());
    rep(500, ['ok' => false, 'error' => 'Connexion à la base de données impossible.']);
}

importerComptesLegacy($pdo);   // migration unique depuis db:users (kv_store)

/* ---------- Helpers ---------- */
function userPublic(array $u): array {
    return [
        'id'        => $u['id'],
        'pseudo'    => $u['pseudo'],
        'email'     => $u['email'],
        'provider'  => $u['provider'],
        'role'      => $u['role'],
        'banned'    => (bool) $u['banned'],
        'stats'     => json_decode($u['stats'] ?: '{"w":0,"l":0,"d":0}', true),
        'createdAt' => (int) $u['created_at'],
    ];
}
function trouveParId(PDO $pdo, string $id): ?array {
    $s = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}
function sessionUser(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return trouveParId($pdo, (string) $_SESSION['user_id']);
}
function exigeAdmin(PDO $pdo): array {
    $u = sessionUser($pdo);
    if (!$u || $u['role'] !== 'admin') rep(403, ['ok' => false, 'error' => 'Action réservée aux administrateurs.']);
    return $u;
}
function emailValide($e): bool { return is_string($e) && preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/', $e) === 1; }
function pseudoNettoye($v): string { return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $v), 0, 16); }
function nouvelId(): string { return 'u' . bin2hex(random_bytes(6)); }
function maintenantMs(): int { return (int) round(microtime(true) * 1000); }
function statsVides(): string { return json_encode(['w' => 0, 'l' => 0, 'd' => 0]); }

try {
    /* ---------- Inscription ---------- */
    if ($action === 'register') {
        $pseudo = pseudoNettoye($req['pseudo'] ?? '');
        $email  = strtolower(trim((string) ($req['email'] ?? '')));
        $pass   = (string) ($req['password'] ?? '');
        if (strlen($pseudo) < 2) rep(400, ['ok' => false, 'error' => 'Pseudo : 2 à 16 caractères (lettres, chiffres, - _).']);
        if (!emailValide($email)) rep(400, ['ok' => false, 'error' => 'Adresse e-mail invalide.']);
        if (strlen($pass) < 6)    rep(400, ['ok' => false, 'error' => 'Mot de passe : 6 caractères minimum.']);

        $s = $pdo->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ?'); $s->execute([$pseudo]);
        if ((int) $s->fetchColumn() > 0) rep(409, ['ok' => false, 'error' => 'Ce pseudo est déjà pris.']);
        $s = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?'); $s->execute([$email]);
        if ((int) $s->fetchColumn() > 0) rep(409, ['ok' => false, 'error' => 'Un compte existe déjà avec cet e-mail.']);

        $premier = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        $id = nouvelId();
        $st = $pdo->prepare('INSERT INTO users (id, pseudo, email, provider, role, pass_hash, banned, stats, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)');
        $st->execute([$id, $pseudo, $email, 'local', $premier ? 'admin' : 'user',
                      password_hash($pass, PASSWORD_DEFAULT), statsVides(), maintenantMs()]);
        $_SESSION['user_id'] = $id;
        rep(200, ['ok' => true, 'user' => userPublic(trouveParId($pdo, $id))]);
    }

    /* ---------- Connexion ---------- */
    if ($action === 'login') {
        $idf  = strtolower(trim((string) ($req['identifiant'] ?? '')));
        $pass = (string) ($req['password'] ?? '');
        if ($idf === '' || $pass === '') rep(400, ['ok' => false, 'error' => 'Renseigne ton identifiant et ton mot de passe.']);
        $s = $pdo->prepare('SELECT * FROM users WHERE LOWER(pseudo) = ? OR email = ? LIMIT 1');
        $s->execute([$idf, $idf]);
        $u = $s->fetch();
        if (!$u) rep(404, ['ok' => false, 'error' => 'Compte introuvable.']);
        if ((int) $u['banned'] === 1) rep(403, ['ok' => false, 'error' => 'Compte suspendu par l’administration.']);
        if ($u['provider'] !== 'local') rep(400, ['ok' => false, 'error' => 'Ce compte utilise la connexion ' . $u['provider'] . '.']);

        $ok = false;
        if (!empty($u['pass_hash'])) {
            $ok = password_verify($pass, $u['pass_hash']);
        } elseif (!empty($u['legacy_hash'])) {
            $ok = verifLegacy($u['legacy_salt'], $pass, $u['legacy_hash']);
            if ($ok) {  // migration transparente vers password_hash
                $pdo->prepare('UPDATE users SET pass_hash = ?, legacy_hash = NULL, legacy_salt = NULL WHERE id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            }
        }
        if (!$ok) rep(401, ['ok' => false, 'error' => 'Mot de passe incorrect.']);
        $_SESSION['user_id'] = $u['id'];
        rep(200, ['ok' => true, 'user' => userPublic($u)]);
    }

    /* ---------- Déconnexion ---------- */
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        rep(200, ['ok' => true]);
    }

    /* ---------- Session courante ---------- */
    if ($action === 'me') {
        $u = sessionUser($pdo);
        rep(200, ['ok' => true, 'user' => $u ? userPublic($u) : null]);
    }

    /* ---------- Liste publique (admin + lobby) ---------- */
    if ($action === 'list') {
        $rows = $pdo->query('SELECT * FROM users ORDER BY created_at ASC')->fetchAll();
        rep(200, ['ok' => true, 'users' => array_map('userPublic', $rows)]);
    }

    /* ---------- Revendication initiale du rôle admin ---------- */
    if ($action === 'claimAdmin') {
        $u = sessionUser($pdo);
        if (!$u) rep(403, ['ok' => false, 'error' => 'Connexion requise.']);
        if ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() > 0)
            rep(409, ['ok' => false, 'error' => 'Un administrateur existe déjà.']);
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$u['id']]);
        rep(200, ['ok' => true, 'user' => userPublic(trouveParId($pdo, $u['id']))]);
    }

    /* ---------- Opérations admin ---------- */
    if ($action === 'adminUpdate') {
        $admin = exigeAdmin($pdo);
        $targetId = (string) ($req['targetId'] ?? '');
        $op = (string) ($req['op'] ?? '');
        $t = trouveParId($pdo, $targetId);
        if (!$t) rep(404, ['ok' => false, 'error' => 'Utilisateur introuvable.']);
        switch ($op) {
            case 'ban':
                if ($t['id'] === $admin['id']) rep(400, ['ok' => false, 'error' => 'Impossible de se suspendre soi-même.']);
                $pdo->prepare('UPDATE users SET banned = 1 WHERE id = ?')->execute([$targetId]);
                break;
            case 'unban':
                $pdo->prepare('UPDATE users SET banned = 0 WHERE id = ?')->execute([$targetId]);
                break;
            case 'promote':
                $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
                break;
            case 'demote':
                $nbAdmin = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($t['role'] === 'admin' && $nbAdmin <= 1)
                    rep(400, ['ok' => false, 'error' => 'Impossible de rétrograder le dernier administrateur.']);
                $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$targetId]);
                break;
            case 'resetstats':
                $pdo->prepare('UPDATE users SET stats = ? WHERE id = ?')->execute([statsVides(), $targetId]);
                break;
            case 'delete':
                if ($t['id'] === $admin['id']) rep(400, ['ok' => false, 'error' => 'Impossible de supprimer son propre compte.']);
                $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$targetId]);
                break;
            default:
                rep(400, ['ok' => false, 'error' => 'Opération inconnue.']);
        }
        rep(200, ['ok' => true]);
    }

    /* ---------- Statistiques du compte de la session ---------- */
    if ($action === 'recordResult') {
        $u = sessionUser($pdo);
        if (!$u) rep(403, ['ok' => false, 'error' => 'Connexion requise.']);
        $gameId  = (string) ($req['gameId'] ?? '');
        $outcome = (string) ($req['outcome'] ?? '');   // 'w' | 'l' | 'd'
        if ($gameId === '' || !in_array($outcome, ['w', 'l', 'd'], true))
            rep(400, ['ok' => false, 'error' => 'Paramètres invalides.']);
        try {
            $pdo->prepare('INSERT INTO stat_records (game_id, user_id) VALUES (?, ?)')->execute([$gameId, $u['id']]);
        } catch (PDOException $e) {
            rep(200, ['ok' => true, 'already' => true]);   // déjà comptabilisé
        }
        $stats = json_decode($u['stats'] ?: statsVides(), true);
        $stats[$outcome] = ($stats[$outcome] ?? 0) + 1;
        $pdo->prepare('UPDATE users SET stats = ? WHERE id = ?')->execute([json_encode($stats), $u['id']]);
        rep(200, ['ok' => true, 'stats' => $stats]);
    }

    /* ---------- Connexion démo SSO ---------- */
    if ($action === 'ssoLogin') {
        $provider = in_array(($req['provider'] ?? ''), ['google', 'facebook'], true) ? $req['provider'] : null;
        $email    = strtolower(trim((string) ($req['email'] ?? '')));
        $pseudo   = pseudoNettoye($req['pseudo'] ?? '');
        if (!$provider || !emailValide($email) || strlen($pseudo) < 2)
            rep(400, ['ok' => false, 'error' => 'Paramètres SSO invalides.']);
        $s = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u) {
            if ((int) $u['banned'] === 1) rep(403, ['ok' => false, 'error' => 'Compte suspendu par l’administration.']);
            $_SESSION['user_id'] = $u['id'];
            rep(200, ['ok' => true, 'user' => userPublic($u)]);
        }
        $s = $pdo->prepare('SELECT COUNT(*) FROM users WHERE pseudo = ?'); $s->execute([$pseudo]);
        if ((int) $s->fetchColumn() > 0) $pseudo = substr($pseudo, 0, 12) . random_int(10, 99);
        $premier = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
        $id = nouvelId();
        $pdo->prepare('INSERT INTO users (id, pseudo, email, provider, role, banned, stats, created_at)
                       VALUES (?, ?, ?, ?, ?, 0, ?, ?)')
            ->execute([$id, $pseudo, $email, $provider, $premier ? 'admin' : 'user', statsVides(), maintenantMs()]);
        $_SESSION['user_id'] = $id;
        rep(200, ['ok' => true, 'user' => userPublic(trouveParId($pdo, $id))]);
    }

    rep(400, ['ok' => false, 'error' => 'Action inconnue.']);
} catch (PDOException $e) {
    error_log('[le-cercle/auth] requête : ' . $e->getMessage());
    rep(500, ['ok' => false, 'error' => 'Erreur lors de l\'accès aux données.']);
}

/* ============================================================
   Fonctions de migration / vérification legacy
============================================================ */

/* Vérifie un mot de passe contre l'ancien schéma de hachage client
   (SHA-256 de "salt::pass", ou repli djb2). */
function verifLegacy($salt, string $pass, string $hash): bool {
    $txt = ((string) $salt) . '::' . $pass;
    if (strpos($hash, 'djb2_') === 0) {
        $h = 5381;
        $len = strlen($txt);
        for ($i = 0; $i < $len; $i++) $h = (($h * 33) + ord($txt[$i])) & 0xFFFFFFFF;
        return hash_equals($hash, 'djb2_' . dechex($h));
    }
    return hash_equals($hash, hash('sha256', $txt));
}

/* Importe une seule fois les comptes de l'ancienne base JSON db:users
   (kv_store) dans la table users, en conservant le hachage legacy. */
function importerComptesLegacy(PDO $pdo): void {
    if ((int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) return;
    $m = $pdo->query("SELECT 1 FROM kv_store WHERE cle = 'auth:migrated' AND scope = '' LIMIT 1");
    if ($m->fetch()) return;   // migration déjà effectuée

    $s = $pdo->query("SELECT valeur FROM kv_store WHERE cle = 'db:users' AND scope = '' LIMIT 1");
    $row = $s->fetch();
    if ($row) {
        $users = json_decode($row['valeur'], true);
        if (is_array($users)) {
            $ins = $pdo->prepare('INSERT IGNORE INTO users
                (id, pseudo, email, provider, role, pass_hash, legacy_salt, legacy_hash, banned, stats, created_at)
                VALUES (?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?)');
            foreach ($users as $u) {
                if (empty($u['pseudo'])) continue;
                $ins->execute([
                    $u['id'] ?? ('u' . bin2hex(random_bytes(6))),
                    substr((string) $u['pseudo'], 0, 16),
                    isset($u['email']) ? strtolower((string) $u['email']) : null,
                    $u['provider'] ?? 'local',
                    (($u['role'] ?? 'user') === 'admin') ? 'admin' : 'user',
                    $u['salt'] ?? null,
                    $u['passHash'] ?? null,
                    !empty($u['banned']) ? 1 : 0,
                    json_encode($u['stats'] ?? ['w' => 0, 'l' => 0, 'd' => 0]),
                    (int) ($u['createdAt'] ?? round(microtime(true) * 1000)),
                ]);
            }
        }
    }
    $pdo->exec("INSERT INTO kv_store (cle, scope, valeur) VALUES ('auth:migrated', '', '1')
                ON DUPLICATE KEY UPDATE valeur = '1'");
}
