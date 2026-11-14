<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Role;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\User\AuthService;
use LeCercle\User\User;

final class AuthServiceThrottleTest extends AuthServiceTestCase
{
    private const ADDRESS = '198.51.100.7';

    protected function setUp(): void
    {
        parent::setUp();
        $this->users->add(User::registered('u1', 'alice', 'alice@x.fr', 'plain:secret1', Role::User, $this->clock->now()));
    }

    private function failLogin(string $identifier = 'alice', string $address = self::ADDRESS): void
    {
        try {
            $this->auth->login($identifier, 'mauvais', $address);
            self::fail('connexion acceptée');
        } catch (ForbiddenException) {
        }
    }

    private function assertLocked(string $identifier, string $password, string $address = self::ADDRESS): void
    {
        try {
            $this->auth->login($identifier, $password, $address);
            self::fail('connexion acceptée malgré le blocage');
        } catch (TooManyRequestsException $e) {
            self::assertSame(AuthService::TOO_MANY, $e->getMessage());
        }
        self::assertNull($this->session->id());
    }

    public function testFiveFailuresLockTheIdentifierEvenWithTheRightPassword(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failLogin();
        }

        $this->assertLocked('alice', 'secret1');
        $this->assertLocked('ALICE@x.fr', 'secret1', '192.0.2.1');
    }

    public function testFourFailuresThenSuccessClearsTheIdentifier(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->failLogin();
        }
        self::assertSame('u1', $this->auth->login('alice', 'secret1', self::ADDRESS)['id']);
        $this->auth->logout();

        for ($i = 0; $i < 4; $i++) {
            $this->failLogin();
        }
        self::assertSame('u1', $this->auth->login('alice', 'secret1', self::ADDRESS)['id']);
    }

    public function testTheAddressLocksAfterTwentyFailures(): void
    {
        for ($i = 0; $i < 19; $i++) {
            $this->failLogin('inconnu' . $i);
        }
        self::assertSame('u1', $this->auth->login('alice', 'secret1', self::ADDRESS)['id']);
        $this->auth->logout();

        $this->failLogin('inconnu19');

        $this->assertLocked('alice', 'secret1');
        self::assertSame('u1', $this->auth->login('alice', 'secret1', '192.0.2.1')['id']);
    }

    public function testFailuresOlderThanTheWindowDoNotCount(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failLogin();
        }
        $this->clock->advanceMs(AuthService::WINDOW_MS);

        self::assertSame('u1', $this->auth->login('alice', 'secret1', self::ADDRESS)['id']);
    }

    public function testUnknownIdentifiersAreCountedToo(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->failLogin('personne');
        }

        $this->assertLocked('personne', 'x');
    }

    public function testIdentifierAndAddressAreStoredHashed(): void
    {
        $this->failLogin();

        foreach ($this->attempts->keys() as $key) {
            self::assertStringNotContainsString('alice', $key);
            self::assertStringNotContainsString(self::ADDRESS, $key);
        }
        self::assertCount(2, $this->attempts->keys());
    }

    public function testWithoutAddressOnlyTheIdentifierIsCounted(): void
    {
        $this->failLogin('alice', '');

        self::assertCount(1, $this->attempts->keys());
    }
}
