<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Stockage des commentaires en direct sur une partie. */
interface GameCommentRepository
{
    public function add(string $gameId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage;

    /** @return list<ChatMessage> les $limit plus récents, du plus ancien au plus récent */
    public function latest(string $gameId, int $limit): array;
}
