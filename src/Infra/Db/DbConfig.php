<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use InvalidArgumentException;
use RuntimeException;

/**
 * Paramètres de connexion à la base : variables d'environnement d'abord
 * (Docker), sinon api/config.php (WampServer).
 */
final readonly class DbConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $name,
        public string $user,
        public string $password,
        public string $charset = 'utf8mb4',
    ) {
        // Ces valeurs finissent dans le DSN : tout caractère de contrôle (« ; », « = »,
        // espace, retour ligne) permettrait d'injecter une option.
        if (preg_match('/\A[A-Za-z0-9.-]{1,253}\z/', $host) !== 1) {
            throw new InvalidArgumentException('Hôte de base de données invalide.');
        }
        if (preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $name) !== 1) {
            throw new InvalidArgumentException('Nom de base de données invalide.');
        }
        if (preg_match('/\A[a-z0-9]{1,32}\z/', $charset) !== 1) {
            throw new InvalidArgumentException('Jeu de caractères invalide.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Port de base de données invalide.');
        }
    }

    /**
     * @param array<string, string> $env
     */
    public static function fromEnvironment(array $env): ?self
    {
        $host = $env['DB_HOST'] ?? '';
        if ($host === '') {
            return null;
        }

        $port = $env['DB_PORT'] ?? '3306';
        if (preg_match('/\A[0-9]{1,5}\z/', $port) !== 1) {
            throw new InvalidArgumentException('Port de base de données invalide.');
        }

        $user = $env['DB_USER'] ?? '';
        if ($user === '') {
            throw new InvalidArgumentException('DB_USER est obligatoire.');
        }

        return new self($host, (int) $port, $env['DB_NAME'] ?? '', $user, $env['DB_PASS'] ?? '');
    }

    public static function fromFile(string $path): self
    {
        $data = require $path;
        if (!is_array($data)) {
            throw new InvalidArgumentException('Le fichier de configuration doit retourner un tableau.');
        }

        foreach (['db_host', 'db_name', 'db_user'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || $data[$key] === '') {
                throw new InvalidArgumentException("Clé de configuration manquante : $key.");
            }
        }
        // Un port saisi à la main dans config.php reste accepté sous forme de chiffres (cf. api/auth.php).
        $port = $data['db_port'] ?? 3306;
        if (is_string($port) && preg_match('/\A[0-9]{1,5}\z/', $port) === 1) {
            $port = (int) $port;
        }
        $password = $data['db_pass'] ?? '';
        $charset = $data['db_charset'] ?? 'utf8mb4';
        if (is_string($charset)) {
            $charset = strtolower($charset);
        }
        if (!is_int($port) || !is_string($password) || !is_string($charset)) {
            throw new InvalidArgumentException('Type de configuration invalide.');
        }

        return new self($data['db_host'], $port, $data['db_name'], $data['db_user'], $password, $charset);
    }

    /**
     * @param array<string, string> $env
     */
    public static function load(array $env, string $configFile): self
    {
        $fromEnv = self::fromEnvironment($env);
        if ($fromEnv !== null) {
            return $fromEnv;
        }
        if (is_file($configFile)) {
            return self::fromFile($configFile);
        }

        throw new RuntimeException(
            'Configuration absente : définir DB_HOST… ou copier api/config.example.php vers api/config.php.',
        );
    }

    public function withName(string $name): self
    {
        return new self($this->host, $this->port, $name, $this->user, $this->password, $this->charset);
    }

    public function dsn(): string
    {
        return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $this->host, $this->port, $this->name, $this->charset);
    }
}
