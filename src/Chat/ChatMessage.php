<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Un message, de salon, privé ou de commentaire de partie. */
final readonly class ChatMessage
{
    public function __construct(
        public int $id,
        public string $authorId,
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {}
}
