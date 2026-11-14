<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Chat\Room;
use LeCercle\Tests\Support\InMemoryRoomRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryRoomRepositoryTest extends TestCase
{
    private function room(string $id, string $name, int $position): Room
    {
        return new Room($id, $name, '', $position, false, null, new \DateTimeImmutable('2026-11-12 10:00:00'));
    }

    public function testNextIdIsSequential(): void
    {
        $repo = new InMemoryRoomRepository();

        self::assertSame('r000000000001', $repo->nextId());
        self::assertSame('r000000000002', $repo->nextId());
    }

    public function testAllIsOrderedByPositionThenId(): void
    {
        $repo = new InMemoryRoomRepository();
        $repo->add($this->room('rb', 'B2', 1));
        $repo->add($this->room('ra', 'A1', 1));
        $repo->add($this->room('general', 'Général', 0));

        self::assertSame(['general', 'ra', 'rb'], array_map(static fn(Room $r): string => $r->id, $repo->all()));
    }

    public function testNameTakenIgnoresCaseAndTheExcludedRoom(): void
    {
        $repo = new InMemoryRoomRepository();
        $repo->add($this->room('ra', 'Analyses', 1));

        self::assertTrue($repo->nameTaken('ANALYSES'));
        self::assertTrue($repo->nameTaken('analyses', 'rz'));
        self::assertFalse($repo->nameTaken('analyses', 'ra'));
        self::assertFalse($repo->nameTaken('Autre'));
    }

    public function testMaxPositionAndDelete(): void
    {
        $repo = new InMemoryRoomRepository();
        self::assertSame(-1, $repo->maxPosition());
        $repo->add($this->room('ra', 'A1', 4));
        self::assertSame(4, $repo->maxPosition());
        $repo->delete('ra');
        self::assertNull($repo->find('ra'));
        self::assertSame(-1, $repo->maxPosition());
    }
}
