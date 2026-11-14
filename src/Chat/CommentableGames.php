<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Dit si une partie peut recevoir des commentaires : une partie en ligne qui existe. */
interface CommentableGames
{
    public function exists(string $gameId): bool;
}
