<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Une conversation privée vue par un joueur : l'interlocuteur, le dernier message, les non-lus. */
final readonly class ThreadSummary
{
    public function __construct(public string $peerId, public ChatMessage $last, public int $unread) {}
}
