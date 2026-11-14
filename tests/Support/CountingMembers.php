<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Member;
use LeCercle\Shared\Members;

/** Espion : compte les appels de lecture. */
final class CountingMembers implements Members
{
    public int $findCalls = 0;
    public int $findManyCalls = 0;

    public function __construct(private readonly Members $inner) {}

    public function find(string $id): ?Member
    {
        ++$this->findCalls;

        return $this->inner->find($id);
    }

    public function findByPseudo(string $pseudo): ?Member
    {
        return $this->inner->findByPseudo($pseudo);
    }

    public function findMany(array $ids): array
    {
        ++$this->findManyCalls;

        return $this->inner->findMany($ids);
    }
}
