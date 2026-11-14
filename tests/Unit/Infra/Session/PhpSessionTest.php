<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Session;

use LeCercle\Infra\Session\PhpSession;
use LeCercle\Shared\ForbiddenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/** Chaque test tourne dans son propre processus : la session PHP est globale. */
#[RunTestsInSeparateProcesses]
final class PhpSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $savePath = \dirname(__DIR__, 4) . '/build/phpunit-sessions';
        if (!is_dir($savePath)) {
            mkdir($savePath, 0o777, true);
        }
        ini_set('session.use_cookies', '0');
        ini_set('session.cache_limiter', '');
        ini_set('session.save_path', $savePath);
    }

    public function testReadsTheUserIdWrittenByAuthPhp(): void
    {
        self::assertSame('u42', self::storeThenRead('u42'));
    }

    public function testNoSessionMeansNoUser(): void
    {
        $session = new PhpSession();

        self::assertNull($session->id());
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Connexion requise.');
        $session->requireId();
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidIds(): iterable
    {
        yield 'chaîne vide' => [''];
        yield 'entier' => [42];
        yield 'tableau' => [['x']];
    }

    #[DataProvider('invalidIds')]
    public function testNonStringOrEmptyIdIsNoUser(mixed $stored): void
    {
        self::assertNull(self::storeThenRead($stored));
    }

    public function testRequireIdReturnsTheUser(): void
    {
        self::storeThenRead('u42');

        self::assertSame('u42', (new PhpSession())->requireId());
    }

    public function testLogInRegeneratesTheIdAndStoresTheUser(): void
    {
        session_id('fixe0000');
        (new PhpSession())->logIn('u7');

        self::assertNotSame('fixe0000', session_id());
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('u7', (new PhpSession())->id());
    }

    public function testLogOutForgetsTheUser(): void
    {
        $session = new PhpSession();
        $session->logIn('u7');
        $session->logOut();

        self::assertNull((new PhpSession())->id());
    }

    /** Écrit la clé comme le fait api/auth.php, puis relit avec PhpSession. */
    private static function storeThenRead(mixed $stored): ?string
    {
        session_start();
        $_SESSION['user_id'] = $stored;
        $sid = session_id();
        session_write_close();
        $_SESSION = [];

        session_id((string) $sid);

        return (new PhpSession())->id();
    }
}
