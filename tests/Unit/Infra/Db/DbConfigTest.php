<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Db;

use InvalidArgumentException;
use LeCercle\Infra\Db\DbConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DbConfigTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';

    public function testEnvironmentAsDockerComposeSetsIt(): void
    {
        $config = DbConfig::fromEnvironment([
            'DB_HOST' => 'db', 'DB_NAME' => 'le_cercle', 'DB_USER' => 'u', 'DB_PASS' => 'p',
        ]);

        self::assertNotNull($config);
        self::assertSame('mysql:host=db;port=3306;dbname=le_cercle;charset=utf8mb4', $config->dsn());
    }

    public function testNoHostMeansNoEnvironmentConfig(): void
    {
        self::assertNull(DbConfig::fromEnvironment([]));
        self::assertNull(DbConfig::fromEnvironment(['DB_HOST' => '']));
    }

    public function testLoadPrefersEnvironment(): void
    {
        $config = DbConfig::load(
            ['DB_HOST' => 'db', 'DB_PORT' => '3307', 'DB_NAME' => 'autre', 'DB_USER' => 'u'],
            self::FIXTURES . '/config-valid.php',
        );

        self::assertSame('mysql:host=db;port=3307;dbname=autre;charset=utf8mb4', $config->dsn());
        self::assertSame('', $config->password);
    }

    public function testLoadFallsBackToConfigFile(): void
    {
        $config = DbConfig::load([], self::FIXTURES . '/config-valid.php');

        self::assertSame('mysql:host=127.0.0.1;port=3306;dbname=le_cercle;charset=utf8mb4', $config->dsn());
        self::assertSame('root', $config->user);
    }

    public function testLoadWithoutAnyConfigThrows(): void
    {
        $this->expectException(RuntimeException::class);
        DbConfig::load([], '/nonexistent');
    }

    public function testMissingUserThrows(): void
    {
        try {
            DbConfig::fromEnvironment(['DB_HOST' => 'db', 'DB_NAME' => 'x']);
            self::fail('InvalidArgumentException attendue pour DB_USER absent.');
        } catch (InvalidArgumentException) {
            $this->expectException(InvalidArgumentException::class);
            DbConfig::fromFile(self::FIXTURES . '/config-missing-key.php');
        }
    }

    /**
     * @param array<string, string> $env
     */
    #[DataProvider('injectionProvider')]
    public function testRejectsDsnInjection(array $env): void
    {
        $this->expectException(InvalidArgumentException::class);
        DbConfig::fromEnvironment($env + ['DB_HOST' => 'db', 'DB_NAME' => 'le_cercle', 'DB_USER' => 'u']);
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function injectionProvider(): array
    {
        return [
            'host avec option'       => [['DB_HOST' => 'db;dbname=mysql']],
            'nom avec host'          => [['DB_NAME' => 'le_cercle;host=evil']],
            'nom avec espace'        => [['DB_NAME' => 'a b']],
            'nom vide'               => [['DB_NAME' => '']],
            'port non numérique'     => [['DB_PORT' => '33o6']],
            'port zéro'              => [['DB_PORT' => '0']],
            'port trop grand'        => [['DB_PORT' => '70000']],
            'nom avec retour ligne'  => [['DB_NAME' => "le_cercle\n"]],
        ];
    }

    public function testFileAcceptsDigitStringPortAndUppercaseCharset(): void
    {
        self::assertSame(
            'mysql:host=127.0.0.1;port=3307;dbname=le_cercle;charset=utf8mb4',
            DbConfig::fromFile(self::FIXTURES . '/config-string-port.php')->dsn(),
        );
    }

    public function testFileRejectsNonDigitPort(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DbConfig::fromFile(self::FIXTURES . '/config-bad-port.php');
    }

    public function testWithName(): void
    {
        $config = DbConfig::load([], self::FIXTURES . '/config-valid.php')->withName('le_cercle_test');

        self::assertSame('le_cercle_test', $config->name);
        self::assertSame('127.0.0.1', $config->host);
    }
}
