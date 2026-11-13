<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class InMemoryMembersTest extends TestCase
{
    public function testFindByPseudoIgnoresCase(): void
    {
        $members = new InMemoryMembers(MemberFactory::user());

        self::assertSame('u000000000001', $members->findByPseudo('ALICE')?->id);
        self::assertNull($members->findByPseudo('nobody'));
    }

    public function testFindManyKeepsKnownIdsOnly(): void
    {
        $members = new InMemoryMembers(MemberFactory::user());

        self::assertSame(['u000000000001'], array_keys($members->findMany(['u000000000001', 'nope'])));
    }

    public function testFindManyAcceptsDuplicatedAndKeyedIds(): void
    {
        $members = new InMemoryMembers(MemberFactory::user());

        self::assertSame(['u000000000001'], array_keys($members->findMany([1 => 'u000000000001', 3 => 'u000000000001'])));
    }

    public function testAddThenFind(): void
    {
        $members = new InMemoryMembers();
        $members->add(MemberFactory::admin());

        self::assertTrue($members->find('u000000000009')?->isAdmin());
    }
}
