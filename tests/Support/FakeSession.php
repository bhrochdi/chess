<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\CurrentUser;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\SessionLogin;

/** Double de test : session en mémoire. */
final class FakeSession implements CurrentUser, SessionLogin
{
    public function __construct(private ?string $userId = null) {}

    public function id(): ?string
    {
        return $this->userId;
    }

    public function requireId(): string
    {
        return $this->userId ?? throw new ForbiddenException('Connexion requise.');
    }

    public function logIn(string $userId): void
    {
        $this->userId = $userId;
    }

    public function logOut(): void
    {
        $this->userId = null;
    }
}
