<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Access;
use LeCercle\Tests\Support\FixedGameStats;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryLoginAttempts;
use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\Tests\Support\PlainPasswordHasher;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\AuthService;
use LeCercle\User\UserViews;
use PHPUnit\Framework\TestCase;

/** Câblage commun des tests d'AuthService. */
abstract class AuthServiceTestCase extends TestCase
{
    protected InMemoryUserRepository $users;
    protected FakeSession $session;
    protected FixedClock $clock;
    protected InMemoryLoginAttempts $attempts;
    protected AuthService $auth;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->session = new FakeSession();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-05-06 07:08:09', new \DateTimeZone('UTC')));
        $this->attempts = new InMemoryLoginAttempts();
        $this->auth = $this->service(new PlainPasswordHasher());
    }

    protected function service(\LeCercle\User\PasswordHasher $hasher): AuthService
    {
        return new AuthService(
            $this->users,
            $hasher,
            $this->session,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $this->clock,
            new ImmediateTransactionRunner(),
            new UserViews(new FixedGameStats()),
            $this->attempts,
        );
    }
}
