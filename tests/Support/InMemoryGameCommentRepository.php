<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\GameCommentRepository;

/** Double de test : commentaires de partie en mémoire. */
final class InMemoryGameCommentRepository implements GameCommentRepository
{
    /** @var array<string, list<ChatMessage>> */
    private array $byGame = [];
    private int $counter = 0;

    public function add(string $gameId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $message = new ChatMessage(++$this->counter, $authorId, $text, $at);
        $this->byGame[$gameId][] = $message;

        return $message;
    }

    public function latest(string $gameId, int $limit): array
    {
        return $limit <= 0 ? [] : \array_slice($this->byGame[$gameId] ?? [], -$limit);
    }
}
