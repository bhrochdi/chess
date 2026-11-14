<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Shared;

use LeCercle\Shared\MemberRef;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class MemberRefTest extends TestCase
{
    public function testResolveKnownMember(): void
    {
        $ref = MemberRef::resolve('u1', ['u1' => MemberFactory::user('u1')]);

        self::assertSame(['id' => 'u1', 'pseudo' => 'alice'], $ref->toArray());
    }

    public function testResolveDeletedAccount(): void
    {
        $ref = MemberRef::resolve('u2', []);

        self::assertSame('u2', $ref->id);
        self::assertSame('(compte supprimé)', $ref->pseudo);
    }
}
