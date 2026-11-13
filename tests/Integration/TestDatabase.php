<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration;

use LeCercle\Infra\Db\DbConfig;
use LeCercle\Infra\Db\Migrator;
use LeCercle\Infra\Db\PdoFactory;
use PDO;
use RuntimeException;

/**
 * Accès à la base des tests d'intégration. Refuse toute base dont le nom ne se
 * termine pas par « _test » : les tests vident les tables.
 */
final class TestDatabase
{
    private static ?PDO $pdo = null;

    public static function config(): DbConfig
    {
        $config = DbConfig::fromEnvironment(getenv());
        if ($config === null) {
            throw new RuntimeException('DB_HOST non défini : lancer les tests d\'intégration via Docker.');
        }

        $name = getenv('DB_TEST_NAME') ?: 'le_cercle_test';
        if (preg_match('/\A[A-Za-z0-9_]+_test\z/', $name) !== 1) {
            throw new RuntimeException("Base de test refusée « $name » : le nom doit se terminer par _test.");
        }

        return $config->withName($name);
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $pdo = PdoFactory::create(self::config());
            (new Migrator($pdo, \dirname(__DIR__, 2) . '/sql/migrations'))->migrate();
            self::$pdo = $pdo;
        }

        return self::$pdo;
    }
}
