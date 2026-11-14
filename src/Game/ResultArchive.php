<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Result;

/** Garde le résultat d'une partie en ligne que la purge va supprimer : les statistiques des joueurs lui survivent. */
interface ResultArchive
{
    /** Appelé dans la transaction de la purge ; un compte qui n'existe plus est ignoré. */
    public function keep(string $whiteId, string $blackId, Result $result): void;
}
