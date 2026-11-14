<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Chess\Result;

/** Une rencontre d'une ronde ; un côté absent (null) signifie un bye. */
final readonly class Pairing
{
    public function __construct(
        public int $board,
        public ?string $whiteId,
        public ?string $blackId,
        public ?Result $result = null,
        public ?string $gameId = null,
    ) {}

    public static function bye(int $board, string $playerId): self
    {
        return new self($board, $playerId, null, Result::WhiteWins);
    }

    public function isBye(): bool
    {
        return ($this->whiteId === null) !== ($this->blackId === null);
    }

    /** Port de vainqueurMatch : null pour une nulle ou une rencontre non jouée. */
    public function winner(): ?string
    {
        if ($this->result === Result::WhiteWins) {
            return $this->whiteId;
        }
        if ($this->result === Result::BlackWins) {
            return $this->blackId;
        }
        if ($this->isBye()) {
            return $this->whiteId ?? $this->blackId;
        }

        return null;
    }

    public function withResult(?Result $result): self
    {
        return new self($this->board, $this->whiteId, $this->blackId, $result, $this->gameId);
    }

    public function withGame(string $gameId): self
    {
        return new self($this->board, $this->whiteId, $this->blackId, $this->result, $gameId);
    }

    public function involves(string $playerId): bool
    {
        return $this->whiteId === $playerId || $this->blackId === $playerId;
    }
}
