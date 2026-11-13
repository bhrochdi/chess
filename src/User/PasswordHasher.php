<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Port de hachage des mots de passe. */
interface PasswordHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;

    /** Vrai si l'empreinte doit être recalculée (algorithme ou coût dépassé). */
    public function needsRehash(string $hash): bool;
}
