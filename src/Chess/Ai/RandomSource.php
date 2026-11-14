<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

/** Source d'aléa injectée, pour que le domaine reste déterministe en test. */
interface RandomSource
{
    /** Nombre dans [0, 1). */
    public function float(): float;
}
