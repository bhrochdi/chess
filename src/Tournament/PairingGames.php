<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\TimeControl;

/** Crée la partie d'un appariement et rend son identifiant. */
interface PairingGames
{
    public function create(string $whiteId, string $blackId, ?TimeControl $tc): string;
}
