<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthServiceRegisterTest extends AuthServiceTestCase
{
    public function testFirstAccountIsAdmin(): void
    {
        $alice = $this->auth->register('alice', 'Alice@X.fr', 'secret1');

        self::assertSame('admin', $alice['role']);
        self::assertSame('alice@x.fr', $alice['email']);
        self::assertSame($alice['id'], $this->session->id());

        $bob = $this->auth->register('bob', 'bob@x.fr', 'secret1');
        self::assertSame('user', $bob['role']);
        self::assertSame($bob['id'], $this->session->id());
    }

    public function testDuplicates(): void
    {
        $this->auth->register('alice', 'alice@x.fr', 'secret1');
        $this->auth->logout();

        try {
            $this->auth->register('ALICE', 'other@x.fr', 'secret1');
            self::fail('pseudo dupliqué accepté');
        } catch (ConflictException $e) {
            self::assertSame('Ce pseudo est déjà pris.', $e->getMessage());
        }
        try {
            $this->auth->register('carol', 'alice@x.fr', 'secret1');
            self::fail('e-mail dupliqué accepté');
        } catch (ConflictException $e) {
            self::assertSame('Un compte existe déjà avec cet e-mail.', $e->getMessage());
        }
        self::assertNull($this->session->id());
        self::assertSame(1, \count($this->users->all()));
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function invalidFields(): iterable
    {
        yield 'pseudo' => ['a', 'a@x.fr', 'secret1', 'Pseudo : 2 à 16 caractères (lettres, chiffres, - _).'];
        yield 'e-mail' => ['alice', 'nope', 'secret1', 'Adresse e-mail invalide.'];
        yield 'mot de passe court' => ['alice', 'a@x.fr', '12345', 'Mot de passe : 6 caractères minimum.'];
        yield 'mot de passe trop long' => ['alice', 'a@x.fr', str_repeat('a', 73), 'Mot de passe : 72 caractères maximum.'];
    }

    #[DataProvider('invalidFields')]
    public function testValidation(string $pseudo, string $email, string $password, string $message): void
    {
        try {
            $this->auth->register($pseudo, $email, $password);
            self::fail('saisie invalide acceptée');
        } catch (ValidationException $e) {
            self::assertSame($message, $e->getMessage());
        }
        self::assertSame(0, \count($this->users->all()));
        self::assertNull($this->session->id());
    }

    public function testPasswordIsHashed(): void
    {
        $view = $this->auth->register('alice', 'alice@x.fr', 'secret1');

        self::assertSame('plain:secret1', $this->users->find($view['id'])?->passwordHash);
        self::assertStringNotContainsString('plain:', json_encode($view, JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('passwordHash', $view);
    }

    public function testFirstRegisteredAccountIsAdminEvenAfterAGuest(): void
    {
        $this->auth->guest(null);
        $this->auth->logout();

        $alice = $this->auth->register('alice', 'alice@x.fr', 'secret1');

        self::assertSame('admin', $alice['role']);
    }
}
