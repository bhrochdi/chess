<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Un salon de discussion. */
final readonly class Room
{
    public const DEFAULT_ID = 'general';

    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public int $position,
        public bool $archived,
        public ?string $createdBy,
        public \DateTimeImmutable $createdAt,
    ) {}

    public function isDefault(): bool
    {
        return $this->id === self::DEFAULT_ID;
    }

    public function renamed(string $name): self
    {
        return new self($this->id, $name, $this->description, $this->position, $this->archived, $this->createdBy, $this->createdAt);
    }

    public function withPosition(int $position): self
    {
        return new self($this->id, $this->name, $this->description, $position, $this->archived, $this->createdBy, $this->createdAt);
    }

    public function withArchived(bool $archived): self
    {
        return new self($this->id, $this->name, $this->description, $this->position, $archived, $this->createdBy, $this->createdAt);
    }
}
