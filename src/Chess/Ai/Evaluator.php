<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

use LeCercle\Chess\Position;

/** Note une position en centipions, positive quand elle est favorable aux Blancs. */
interface Evaluator
{
    public function evaluate(Position $p): int;
}
