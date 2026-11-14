<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Stockage des messages privés et des marques de lecture. */
interface DirectMessageRepository
{
    public function add(string $senderId, string $recipientId, string $text, \DateTimeImmutable $at): ChatMessage;

    /** @return list<ChatMessage> les $limit plus récents entre les deux joueurs, du plus ancien au plus récent */
    public function conversation(string $userId, string $peerId, int $limit): array;

    /** @return list<ThreadSummary> une par interlocuteur, dernier message le plus récent d'abord ; non-lus = messages de l'interlocuteur au-dessus de la marque de lecture */
    public function threads(string $userId, int $limit): array;

    /** Total des non-lus de toutes les conversations du joueur. */
    public function unreadTotal(string $userId): int;

    /** Ne fait jamais reculer une marque existante. */
    public function markRead(string $userId, string $peerId, int $upToId): void;
}
