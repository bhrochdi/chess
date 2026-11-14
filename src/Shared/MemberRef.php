<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Référence à un joueur dans les réponses JSON : `{id, pseudo}`. */
final readonly class MemberRef
{
    public const DELETED_PSEUDO = '(compte supprimé)';

    public function __construct(public string $id, public string $pseudo) {}

    /** @param array<string, Member> $members */
    public static function resolve(string $id, array $members): self
    {
        return new self($id, isset($members[$id]) ? $members[$id]->pseudo : self::DELETED_PSEUDO);
    }

    /** @return array{id: string, pseudo: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'pseudo' => $this->pseudo];
    }
}
