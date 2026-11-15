<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\Throttle;

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
        private readonly Throttle $throttle,
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

        $text = MessageText::clean($text);
        $now = $this->clock->now();
        MessageRate::check($this->throttle, $author->id, $now);

        return $this->views->one($this->comments->add($gameId, $author->id, $text, $now));
    }

    private function assertGameId(string $gameId): void
    {
        if (!$this->games->exists($gameId)) {
            throw new NotFoundException('Partie introuvable.');
        }
    }
}
