<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Issue d'une partie terminée : résultat et motif. */
final readonly class Outcome
{
    public function __construct(
        public Result $result,
        public EndReason $reason,
    ) {}
}
