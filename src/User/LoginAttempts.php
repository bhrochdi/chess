<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Échecs de connexion récents, par clé opaque (empreinte d'un identifiant ou d'une adresse). */
interface LoginAttempts
{
    public function failuresSince(string $key, int $sinceMs): int;

    public function record(string $key, int $atMs): void;

    public function clear(string $key): void;
}
