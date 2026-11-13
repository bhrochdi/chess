<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chat\CommentableGames;

/** Double de test : seules les parties données existent. */
final class KnownGames implements CommentableGames
{
    /** @var list<string> */
    private readonly array $ids;

    public function __construct(string ...$ids)
    {
        $this->ids = array_values($ids);
    }

    public function exists(string $gameId): bool
    {
        return \in_array($gameId, $this->ids, true);
    }
}
