<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra;

use LeCercle\Infra\Infrastructure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class InfrastructureHttpsTest extends TestCase
{
    /** @return iterable<string, array{array<mixed>, bool}> */
    public static function servers(): iterable
    {
        yield 'HTTPS=on' => [['HTTPS' => 'on'], true];
        yield 'HTTPS=1' => [['HTTPS' => '1'], true];
        yield 'port 443' => [['SERVER_PORT' => '443'], true];
        yield 'port 443 en entier' => [['SERVER_PORT' => 443], true];
        yield 'HTTPS=off (IIS)' => [['HTTPS' => 'off', 'SERVER_PORT' => '80'], false];
        yield 'HTTPS vide' => [['HTTPS' => ''], false];
        yield 'HTTP simple' => [['SERVER_PORT' => '80'], false];
        yield 'rien' => [[], false];
        yield 'mandataire non cru' => [['HTTP_X_FORWARDED_PROTO' => 'https', 'SERVER_PORT' => '80'], false];
    }

    /** @param array<mixed> $server */
    #[DataProvider('servers')]
    public function testIsHttps(array $server, bool $expected): void
    {
        self::assertSame($expected, Infrastructure::isHttps($server));
    }
}
