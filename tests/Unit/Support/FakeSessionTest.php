<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Shared\ForbiddenException;
use LeCercle\Tests\Support\FakeSession;
use PHPUnit\Framework\TestCase;

final class FakeSessionTest extends TestCase
{
    public function testAnonymousByDefault(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Connexion requise.');
        (new FakeSession())->requireId();
    }

    public function testLogInThenLogOut(): void
    {
        $session = new FakeSession();

        $session->logIn('a');
        self::assertSame('a', $session->id());
        self::assertSame('a', $session->requireId());

        $session->logOut();
        self::assertNull($session->id());
    }

    public function testStartsLoggedInWhenGivenAnId(): void
    {
        self::assertSame('b', (new FakeSession('b'))->id());
    }
}
