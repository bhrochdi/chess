<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Role;
use LeCercle\Shared\ValidationException;
use LeCercle\User\AuthService;
use LeCercle\User\PasswordHasher;
use LeCercle\User\Provider;
use LeCercle\User\User;
use PHPUnit\Framework\Attributes\DataProvider;

final class AuthServiceLoginTest extends AuthServiceTestCase
{
    private function addAlice(): void
    {
        $this->users->add(User::registered('u1', 'alice', 'alice@x.fr', 'plain:secret1', Role::User, $this->clock->now()));
    }

    /** @return array<string, mixed> */
    private function meView(): array
    {
        return $this->auth->me() ?? [];
    }

    public function testLoginByPseudoOrEmailCaseInsensitive(): void
    {
        $this->addAlice();

        self::assertSame('u1', $this->auth->login('ALICE', 'secret1')['id']);
        $this->auth->logout();
        self::assertSame('u1', $this->auth->login(' alice@x.fr ', 'secret1')['id']);
        self::assertSame('u1', $this->session->id());
    }

    /** @return iterable<string, array{string, string}> */
    public static function badCredentials(): iterable
    {
        yield 'inconnu' => ['nobody', 'secret1'];
        yield 'mauvais mot de passe' => ['alice', 'wrong-pass'];
        yield 'invité' => ['Invite_ab12', 'secret1'];
        yield 'fournisseur sans empreinte' => ['gina', 'secret1'];
    }

    #[DataProvider('badCredentials')]
    public function testBadCredentialsAreIndistinguishable(string $identifier, string $password): void
    {
        $this->addAlice();
        $now = $this->clock->now();
        $this->users->add(User::guest('u2', 'Invite_ab12', $now));
        $this->users->add(new User('u3', 'gina', 'g@x.fr', Provider::Google, Role::User, null, false, $now));

        try {
            $this->auth->login($identifier, $password);
            self::fail('connexion acceptée');
        } catch (ForbiddenException $e) {
            self::assertSame('Identifiant ou mot de passe incorrect.', $e->getMessage());
        }
        self::assertNull($this->session->id());
    }

    public function testGuestCannotLogInWithAPassword(): void
    {
        $this->users->add(User::guest('u2', 'Invite_ab12', $this->clock->now()));

        foreach (['', 'plain:', 'secret1', AuthService::BAD_CREDENTIALS] as $password) {
            try {
                $this->auth->login('Invite_ab12', $password);
                self::fail('un invité s’est connecté par mot de passe');
            } catch (ForbiddenException | ValidationException $e) {
                self::assertContains($e->getMessage(), [AuthService::BAD_CREDENTIALS, 'Renseigne ton identifiant et ton mot de passe.']);
            }
        }
        self::assertNull($this->session->id());
    }

    public function testUnknownIdentifierStillVerifiesAgainstADummyHash(): void
    {
        $hasher = new class implements PasswordHasher {
            /** @var list<string> */
            public array $verified = [];

            public function hash(string $password): string
            {
                return 'h:' . $password;
            }

            public function verify(string $password, string $hash): bool
            {
                $this->verified[] = $hash;

                return false;
            }

            public function needsRehash(string $hash): bool
            {
                return false;
            }
        };
        $auth = $this->service($hasher);
        $this->addAlice();

        foreach (['nobody', 'alice'] as $identifier) {
            try {
                $auth->login($identifier, 'secret1');
            } catch (ForbiddenException) {
            }
        }

        self::assertCount(2, $hasher->verified);
        self::assertSame('plain:secret1', $hasher->verified[1]);
        self::assertNotSame('', $hasher->verified[0]);
        self::assertNotSame($hasher->verified[1], $hasher->verified[0]);
    }

    public function testPasswordLongerThan72BytesIsRefusedAfterTheVerification(): void
    {
        $this->users->add(User::registered('u1', 'alice', 'alice@x.fr', 'plain:' . str_repeat('a', 72), Role::User, $this->clock->now()));
        $hasher = new class implements PasswordHasher {
            public int $verifications = 0;

            public function hash(string $password): string
            {
                return 'plain:' . $password;
            }

            public function verify(string $password, string $hash): bool
            {
                ++$this->verifications;

                return true;
            }

            public function needsRehash(string $hash): bool
            {
                return false;
            }
        };
        $auth = $this->service($hasher);

        try {
            $auth->login('alice', str_repeat('a', 73));
            self::fail('mot de passe trop long accepté');
        } catch (ForbiddenException $e) {
            self::assertSame(AuthService::BAD_CREDENTIALS, $e->getMessage());
        }
        self::assertSame(1, $hasher->verifications);
        self::assertNull($this->session->id());
    }

    public function testSuspendedNeedsTheRightPasswordFirst(): void
    {
        $this->users->add(User::registered('u2', 'bob', 'bob@x.fr', 'plain:secret1', Role::User, $this->clock->now())->withSuspended(true));

        try {
            $this->auth->login('bob', 'wrong-pass');
            self::fail('connexion acceptée');
        } catch (ForbiddenException $e) {
            self::assertSame('Identifiant ou mot de passe incorrect.', $e->getMessage());
        }
        try {
            $this->auth->login('bob', 'secret1');
            self::fail('connexion acceptée');
        } catch (ForbiddenException $e) {
            self::assertSame(Access::SUSPENDED, $e->getMessage());
        }
        self::assertNull($this->session->id());
    }

    public function testEmptyFields(): void
    {
        foreach ([[' ', 'x'], ['alice', '']] as [$identifier, $password]) {
            try {
                $this->auth->login($identifier, $password);
                self::fail('champ vide accepté');
            } catch (ValidationException $e) {
                self::assertSame('Renseigne ton identifiant et ton mot de passe.', $e->getMessage());
            }
        }
    }

    public function testRehash(): void
    {
        $this->users->add(User::registered('u1', 'alice', 'alice@x.fr', 'old:secret1', Role::User, $this->clock->now()));

        $this->auth->login('alice', 'secret1');

        self::assertSame('plain:secret1', $this->users->find('u1')?->passwordHash);
        self::assertSame('u1', $this->session->id());
    }

    public function testLogoutAndMe(): void
    {
        $this->addAlice();
        self::assertNull($this->auth->me());

        $this->auth->login('alice', 'secret1');
        self::assertSame('alice', $this->meView()['pseudo'] ?? null);

        $this->auth->logout();
        self::assertNull($this->auth->me());
        self::assertNull($this->session->id());

        $this->session->logIn('u1');
        $this->users->delete('u1');
        self::assertNull($this->auth->me());
    }

    public function testMeStillAnswersForASuspendedAccount(): void
    {
        $this->addAlice();
        $this->users->save($this->users->find('u1')?->withSuspended(true) ?? throw new \LogicException());
        $this->session->logIn('u1');

        $me = $this->auth->me();

        self::assertTrue($me['banned'] ?? null);
        self::assertSame('alice@x.fr', $me['email'] ?? null);
    }
}
