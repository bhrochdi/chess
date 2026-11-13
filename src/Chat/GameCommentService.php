<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;
use LeCercle\Shared\NotFoundException;

/** Commentaires en direct sur une partie en ligne existante. */
final class GameCommentService
{
    public const LIMIT = 80;

    public function __construct(
        private readonly GameCommentRepository $comments,
        private readonly CommentableGames $games,
        private readonly Access $access,
        private readonly Clock $clock,
        private readonly MessageViews $views,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(string $gameId): array
    {
        $this->assertGameId($gameId);

        return $this->views->list($this->comments->latest($gameId, self::LIMIT));
    }

    /** @return array<string, mixed> */
    public function send(string $gameId, string $text): array
    {
        $this->assertGameId($gameId);
        $author = $this->access->member();

        return $this->views->one($this->comments->add($gameId, $author->id, MessageText::clean($text), $this->clock->now()));
    }

    private function assertGameId(string $gameId): void
    {
        if (!$this->games->exists($gameId)) {
            throw new NotFoundException('Partie introuvable.');
        }
    }
}
