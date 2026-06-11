<?php
declare(strict_types=1);

/*
 * Le Cercle — endpoint unique de stockage clé-valeur.
 *
 * Remplace l'API window.storage de l'artefact Claude.ai.
 * Reçoit du JSON en POST :
 *
 *   { "action": "get",    "key": "...",            "shared": true|false }
 *   { "action": "set",    "key": "...", "value": "<json>", "shared": ... }
 *   { "action": "list",   "prefix": "...",         "shared": true|false }
 *   { "action": "delete", "key": "...",            "shared": true|false }
 *
 * scope effectif :
 *   shared = true  -> ''                  (donnée partagée, visible de tous)
 *   shared = false -> id de session PHP   (donnée propre au visiteur)
 *
 * Réponses JSON :
 *   get    -> { ok, value: <chaîne JSON|null> }
 *   set    -> { ok }
 *   list   -> { ok, keys: [...] }
 *   delete -> { ok }
 *   erreur -> { ok:false, error:"..." } + code HTTP adapté
 */

header('Content-Type: application/json; charset=utf-8');

/** Émet une réponse JSON et termine le script. */
function reponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Valide une clé / un préfixe : mêmes règles que le front
 * (pas d'espace, ni slash, ni guillemet), 1 à 200 caractères.
 */
function cleValide($v): bool
{
    return is_string($v)
        && $v !== ''
        && mb_strlen($v) <= 200
        && preg_match('#^[^\s/\\\\"\'`]+$#u', $v) === 1;
}

/* ---- Méthode HTTP ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reponse(405, ['ok' => false, 'error' => 'Méthode non autorisée : POST requis.']);
}

/* ---- Configuration ---- */
$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    reponse(500, ['ok' => false, 'error' => 'Configuration absente : copier config.example.php vers config.php.']);
}
$config = require $configFile;

/* ---- Corps de la requête ---- */
$req = json_decode(file_get_contents('php://input'), true);
if (!is_array($req)) {
    reponse(400, ['ok' => false, 'error' => 'Corps JSON invalide.']);
}

$action = isset($req['action']) ? (string) $req['action'] : '';
if (!in_array($action, ['get', 'set', 'list', 'delete'], true)) {
    reponse(400, ['ok' => false, 'error' => 'Action inconnue.']);
}

/* ---- Détermination du scope ---- */
// Par défaut partagé ; le stockage personnel exige une session PHP.
$shared = !array_key_exists('shared', $req) || $req['shared'] === true || $req['shared'] === 'true';
if ($shared) {
    $scope = '';
} else {
    session_start();
    $scope = session_id();
}

/* ---- Connexion PDO (requêtes préparées) ---- */
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['db_host'],
        (int) ($config['db_port'] ?? 3306),
        $config['db_name'],
        $config['db_charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('[le-cercle] connexion PDO : ' . $e->getMessage());
    reponse(500, ['ok' => false, 'error' => 'Connexion à la base de données impossible.']);
}

/* ---- Traitement des actions ---- */
try {
    if ($action === 'get') {
        if (!cleValide($req['key'] ?? null)) {
            reponse(400, ['ok' => false, 'error' => 'Clé invalide.']);
        }
        $st = $pdo->prepare('SELECT valeur FROM kv_store WHERE cle = ? AND scope = ?');
        $st->execute([$req['key'], $scope]);
        $row = $st->fetch();
        reponse(200, ['ok' => true, 'value' => $row ? $row['valeur'] : null]);
    }

    if ($action === 'set') {
        if (!cleValide($req['key'] ?? null)) {
            reponse(400, ['ok' => false, 'error' => 'Clé invalide.']);
        }
        if (!array_key_exists('value', $req) || !is_string($req['value'])) {
            reponse(400, ['ok' => false, 'error' => 'Valeur manquante (chaîne JSON attendue).']);
        }
        json_decode($req['value']);
        if (json_last_error() !== JSON_ERROR_NONE) {
            reponse(400, ['ok' => false, 'error' => 'Valeur JSON invalide.']);
        }
        $st = $pdo->prepare(
            'INSERT INTO kv_store (cle, scope, valeur) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE valeur = VALUES(valeur), maj = CURRENT_TIMESTAMP'
        );
        $st->execute([$req['key'], $scope, $req['value']]);
        reponse(200, ['ok' => true]);
    }

    if ($action === 'list') {
        if (!cleValide($req['prefix'] ?? null)) {
            reponse(400, ['ok' => false, 'error' => 'Préfixe invalide.']);
        }
        // Échappe les jokers LIKE dans le préfixe (caractère d'échappement MySQL par défaut : \).
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $req['prefix']) . '%';
        $st = $pdo->prepare('SELECT cle FROM kv_store WHERE scope = ? AND cle LIKE ?');
        $st->execute([$scope, $like]);
        $keys = array_map(static fn($r) => $r['cle'], $st->fetchAll());
        reponse(200, ['ok' => true, 'keys' => $keys]);
    }

    if ($action === 'delete') {
        if (!cleValide($req['key'] ?? null)) {
            reponse(400, ['ok' => false, 'error' => 'Clé invalide.']);
        }
        $st = $pdo->prepare('DELETE FROM kv_store WHERE cle = ? AND scope = ?');
        $st->execute([$req['key'], $scope]);
        reponse(200, ['ok' => true]);
    }
} catch (PDOException $e) {
    error_log('[le-cercle] requête : ' . $e->getMessage());
    reponse(500, ['ok' => false, 'error' => 'Erreur lors de l\'accès aux données.']);
}
