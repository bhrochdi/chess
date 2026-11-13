<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use PDO;

/**
 * Ouvre la connexion PDO : exceptions, requêtes préparées natives, session en UTC.
 */
final class PdoFactory
{
    public static function create(DbConfig $config): PDO
    {
        return new PDO($config->dsn(), $config->user, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    }
}
