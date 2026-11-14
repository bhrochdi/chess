<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Role;
use LeCercle\Shared\ValidationException;
use LeCercle\User\Provider;
use LeCercle\User\User;

final class AuthServiceGuestTest extends AuthServiceTestCase
{
    private function addUser(string $id, string $pseudo, Role $role = Role::User): void
    {
        $this->users->add(User::registered($id, $pseudo, $pseudo . '@x.fr', 'plain:secret1', $role, $this->clock->now()));
    }

    public function testGuestWithoutPseudo(): void
    {
        $view = $this->auth->guest(null);

        self::assertMatchesRegularExpression('/\AInvite_[0-9a-f]{8}\z/', $view['pseudo']);
        self::assertNull($view['email']);
        $user = $this->users->find($view['id']);
        self::assertSame(Provider::Guest, $user?->provider);
        self::assertSame($view['id'], $this->session->id());
    }

    public function testGuestWithPseudo(): void
    {
        self::assertSame('Visiteur', $this->auth->guest('Visiteur')['pseudo']);
    }

    public function testGuestPseudoTaken(): void
    {
        $this->addUser('u1', 'alice');

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce pseudo est déjà pris.');
        $this->auth->guest('alice');
    }

    public function testGuestPseudoRule(): void
    {
        $this->expectException(ValidationException::class);
        $this->auth->guest('x');
    }

    public function testGuestWhileLoggedIn(): void
    {
        $this->addUser('u1', 'alice');
        $this->auth->login('alice', 'secret1');

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Tu es déjà connecté.');
        $this->auth->guest(null);
    }

    public function testGuestRetriesWhenTheGeneratedPseudoIsTaken(): void
    {
        $this->users->add(User::guest('u0', 'Invite_deadbeef', $this->clock->now()));
        $this->users->forceNextIds('u0000deadbeef', 'u0000cafe0001');

        $view = $this->auth->guest(null);

        self::assertSame('Invite_cafe0001', $view['pseudo']);
        self::assertSame('u0000cafe0001', $view['id']);
    }

    public function testGuestGivesUpAfterTheAttempts(): void
    {
        $this->users->add(User::guest('u0', 'Invite_deadbeef', $this->clock->now()));
        $this->users->forceNextIds(...array_fill(0, 5, 'u0000deadbeef'));

        try {
            $this->auth->guest(null);
            self::fail('invité créé malgré les collisions');
        } catch (ConflictException $e) {
            self::assertSame('Pseudo d’invité indisponible, réessaie.', $e->getMessage());
        }
        self::assertNull($this->session->id());
        self::assertSame(1, \count($this->users->all()));
    }

    public function testClaimAdmin(): void
    {
        $this->addUser('u1', 'alice');
        $this->addUser('u2', 'bob');
        $this->session->logIn('u1');

        $view = $this->auth->claimAdmin();

        self::assertSame('admin', $view['role']);
        self::assertSame(Role::Admin, $this->users->find('u1')?->role);

        $this->session->logIn('u2');
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Un administrateur existe déjà.');
        $this->auth->claimAdmin();
    }

    public function testClaimAdminRefusedToAGuest(): void
    {
        $this->auth->guest(null);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Réservé aux comptes inscrits.');
        $this->auth->claimAdmin();
    }

    public function testClaimAdminRequiresALogin(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Connexion requise.');
        $this->auth->claimAdmin();
    }
}
