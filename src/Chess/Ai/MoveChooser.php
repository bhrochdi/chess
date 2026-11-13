<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

use LeCercle\Chess\Move;
use LeCercle\Chess\Position;

/** Choisit un coup pour le camp qui a le trait. */
interface MoveChooser
{
    /** @return Move|null `null` quand il n'existe aucun coup légal */
    public function choose(Position $p): ?Move;
}
