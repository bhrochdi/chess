<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\ValidationException;
use LeCercle\User\AuthService;

/** Créations de comptes limitées par adresse du client. */
final class AuthServiceAccountLimitTest extends AuthServiceTestCase
{
    private const ADDRESS = '203.0.113.9';

    private function guests(int $count, string $address): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->auth->guest(null, $address);
            $this->session->logOut();
        }
    }

    private function registrations(int $count, string $address): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->auth->register("joueur$i", "joueur$i@x.fr", 'secret1', $address);
            $this->session->logOut();
        }
    }

    public function testThirtyAccountsPerHourAndAddress(): void
    {
        self::assertSame(
            [30, 30, 3_600_000],
            [AuthService::MAX_GUESTS_PER_ADDRESS, AuthService::MAX_REGISTRATIONS_PER_ADDRESS, AuthService::ACCOUNTS_WINDOW_MS],
        );
    }

    public function testGuestBeyondTheLimitIsRefusedAndNotCreated(): void
    {
        $this->guests(AuthService::MAX_GUESTS_PER_ADDRESS, self::ADDRESS);

        try {
            $this->auth->guest(null, self::ADDRESS);
            self::fail('Le compte invité devait être refusé.');
        } catch (TooManyRequestsException $e) {
            self::assertSame('Trop de comptes invités créés depuis cette connexion. Réessaie plus tard.', $e->getMessage());
        }
        self::assertCount(AuthService::MAX_GUESTS_PER_ADDRESS, $this->users->all());
        self::assertNull($this->session->id());
    }

    public function testRegistrationBeyondTheLimitIsRefusedAndNotCreated(): void
    {
        $this->registrations(AuthService::MAX_REGISTRATIONS_PER_ADDRESS, self::ADDRESS);

        try {
            $this->auth->register('dernier', 'dernier@x.fr', 'secret1', self::ADDRESS);
            self::fail('L’inscription devait être refusée.');
        } catch (TooManyRequestsException $e) {
            self::assertSame('Trop d’inscriptions depuis cette connexion. Réessaie plus tard.', $e->getMessage());
        }
        self::assertCount(AuthService::MAX_REGISTRATIONS_PER_ADDRESS, $this->users->all());
        self::assertNull($this->session->id());
    }

    public function testGuestsAndRegistrationsAreCountedSeparately(): void
    {
        $this->guests(AuthService::MAX_GUESTS_PER_ADDRESS, self::ADDRESS);

        self::assertSame('alice', $this->auth->register('alice', 'alice@x.fr', 'secret1', self::ADDRESS)['pseudo']);
    }

    public function testAnotherAddressIsNotAffected(): void
    {
        $this->guests(AuthService::MAX_GUESTS_PER_ADDRESS, self::ADDRESS);

        self::assertSame('Visiteur', $this->auth->guest('Visiteur', '198.51.100.7')['pseudo']);
    }

    public function testCreationResumesOnceTheHourHasPassed(): void
    {
        $this->guests(AuthService::MAX_GUESTS_PER_ADDRESS, self::ADDRESS);
        $this->clock->advanceMs(AuthService::ACCOUNTS_WINDOW_MS);

        self::assertSame('Visiteur', $this->auth->guest('Visiteur', self::ADDRESS)['pseudo']);
    }

    public function testWithoutAKnownAddressNothingIsCounted(): void
    {
        $this->guests(AuthService::MAX_GUESTS_PER_ADDRESS + 5, '');

        self::assertCount(AuthService::MAX_GUESTS_PER_ADDRESS + 5, $this->users->all());
    }

    public function testAnInvalidRequestDoesNotCount(): void
    {
        for ($i = 0; $i < AuthService::MAX_GUESTS_PER_ADDRESS + 5; ++$i) {
            try {
                $this->auth->guest('x', self::ADDRESS);
                self::fail('Un pseudo trop court devait être refusé.');
            } catch (ValidationException) {
            }
        }

        self::assertSame('Visiteur', $this->auth->guest('Visiteur', self::ADDRESS)['pseudo']);
    }
}
