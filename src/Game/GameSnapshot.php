<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Shared\TimeControl;

/** Tout ce que le dépôt stocke d'une partie ; la position, les prises et les répétitions s'en déduisent par rejeu. */
final readonly class GameSnapshot
{
    /** @param list<PlayedMove> $moves */
    public function __construct(
        public string $id,
        public GameMode $mode,
        public string $ownerId,
        public ?string $whiteId,
        public ?string $blackId,
        public ?AiLevel $level,
        public ?TimeControl $timeControl,
        public GameStatus $status,
        public array $moves,
        public ?GameClock $clock,
        public ?Color $drawOffer,
        public ?Result $result,
        public ?FinishReason $reason,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {}
}
