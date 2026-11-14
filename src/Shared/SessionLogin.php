<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Ouvre et ferme la session de l'utilisateur. */
interface SessionLogin
{
    public function logIn(string $userId): void;

    public function logOut(): void;
}
