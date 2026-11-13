<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\User\PasswordHasher;

/**
 * Hacheur de test, sans coût de calcul : « plain:<mot de passe> ».
 * Le préfixe « old: » simule une ancienne empreinte, vérifiable mais à recalculer.
 */
final class PlainPasswordHasher implements PasswordHasher
{
    public function hash(string $password): string
    {
        return 'plain:' . $password;
    }

    public function verify(string $password, string $hash): bool
    {
        return hash_equals($this->hash($password), $hash)
            || hash_equals('old:' . $password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return str_starts_with($hash, 'old:');
    }
}
