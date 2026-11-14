<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Result;
use LeCercle\Shared\TimeControl;

/** Ce que les listes de parties montrent d'une partie : sièges, état et fin, sans la position. */
final readonly class GameSummary
{
    public function __construct(
        public string $id,
        public GameMode $mode,
        public GameStatus $status,
        public string $ownerId,
        public ?string $whiteId,
        public ?string $blackId,
        public ?AiLevel $level,
        public ?TimeControl $timeControl,
        public ?Result $result,
        public ?FinishReason $reason,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function of(Game $game): self
    {
        return new self(
            $game->id(),
            $game->mode(),
            $game->status(),
            $game->ownerId(),
            $game->whiteId(),
            $game->blackId(),
            $game->level(),
            $game->timeControl(),
            $game->result(),
            $game->reason(),
            $game->updatedAt(),
        );
    }
}
