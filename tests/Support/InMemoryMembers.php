<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Member;
use LeCercle\Shared\Members;

/** Double de test : membres en mémoire. */
final class InMemoryMembers implements Members
{
    /** @var array<string, Member> */
    private array $byId = [];

    public function __construct(Member ...$members)
    {
        foreach ($members as $member) {
            $this->add($member);
        }
    }

    public function add(Member $m): void
    {
        $this->byId[$m->id] = $m;
    }

    public function find(string $id): ?Member
    {
        return $this->byId[$id] ?? null;
    }

    public function findByPseudo(string $pseudo): ?Member
    {
        foreach ($this->byId as $member) {
            if (strtolower($member->pseudo) === strtolower($pseudo)) {
                return $member;
            }
        }

        return null;
    }

    /** @param array<int, string> $ids */
    public function findMany(array $ids): array
    {
        $found = [];
        foreach (array_unique($ids) as $id) {
            if (isset($this->byId[$id])) {
                $found[$id] = $this->byId[$id];
            }
        }

        return $found;
    }
}
