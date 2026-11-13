<?php

declare(strict_types=1);

namespace LeCercle\Infra\User;

use LeCercle\User\PasswordHasher;

/** Hachage réel : algorithme par défaut de PHP (bcrypt en 8.3). */
final class PhpPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
