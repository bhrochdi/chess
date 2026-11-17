<?php

declare(strict_types=1);

namespace LeCercle\Game;

/**
 * État de la partie après un demi-coup donné, pour la relecture coup par coup ; le rang 0 est la
 * position de départ. Les temps sont ceux des pendules après le coup, null sans cadence.
 */
final readonly class GameFrame
{
    /** @param array{w: list<string>, b: list<string>} $captured lettres FEN des pièces prises par chaque camp */
    public function __construct(
        public string $fen,
        public bool $inCheck,
        public ?string $lastUci,
        public array $captured,
        public ?int $whiteMs,
        public ?int $blackMs,
    ) {}
}
