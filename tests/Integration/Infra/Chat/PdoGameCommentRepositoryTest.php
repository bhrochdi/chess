<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Infra\Chat\PdoGameCommentRepository;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoGameCommentRepositoryTest extends DatabaseTestCase
{
    public function testAddAndLatestRoundTripScopedByGame(): void
    {
        $repo = new PdoGameCommentRepository(self::pdo());
        $at = new \DateTimeImmutable('2026-11-12 10:00:00.789', new \DateTimeZone('UTC'));

        $first = $repo->add('g1', 'u1', 'beau coup', $at);
        $repo->add('g1', 'u2', 'merci', $at);
        $repo->add('g2', 'u1', 'autre partie', $at);

        self::assertSame('2026-11-12 10:00:00.789', $first->createdAt->format('Y-m-d H:i:s.v'));
        $texts = array_map(static fn($m): string => $m->text, $repo->latest('g1', 50));
        self::assertSame(['beau coup', 'merci'], $texts);
        $last = array_map(static fn($m): string => $m->text, $repo->latest('g1', 1));
        self::assertSame(['merci'], $last);
        self::assertSame([], $repo->latest('g3', 50));
    }
}
