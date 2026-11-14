<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\MessageViews;
use LeCercle\Chat\Room;
use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class MessageViewsTest extends TestCase
{
    private const MS = 1794477600123;
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';

    private function spy(): CountingMembers
    {
        return new CountingMembers(new InMemoryMembers(MemberFactory::user(self::ALICE, 'alice')));
    }

    private function views(): MessageViews
    {
        return new MessageViews($this->spy());
    }

    private function msg(int $id, string $author): ChatMessage
    {
        return new ChatMessage($id, $author, 'Bonjour', Millis::toDateTime(self::MS));
    }

    public function testOne(): void
    {
        self::assertSame(
            ['id' => 42, 'author' => ['id' => self::ALICE, 'pseudo' => 'alice'], 'text' => 'Bonjour', 'createdAt' => self::MS],
            $this->views()->one($this->msg(42, self::ALICE)),
        );
    }

    public function testListUsesOneFindManyAndMarksDeletedAuthor(): void
    {
        $spy = $this->spy();
        $views = new MessageViews($spy);
        $out = $views->list([$this->msg(1, self::ALICE), $this->msg(2, self::BOB), $this->msg(3, self::ALICE)]);

        self::assertSame(1, $spy->findManyCalls);
        self::assertSame(0, $spy->findCalls);
        self::assertSame('alice', $out[0]['author']['pseudo']);
        self::assertSame('(compte supprimé)', $out[1]['author']['pseudo']);
        self::assertSame(self::BOB, $out[1]['author']['id']);
        self::assertSame(3, $out[2]['id']);
    }

    public function testRoomSeededHasNullCreator(): void
    {
        $room = new Room('general', 'Général', 'Discussion générale du Cercle', 0, false, null, Millis::toDateTime(self::MS));
        self::assertSame(
            [
                'id' => 'general', 'name' => 'Général', 'description' => 'Discussion générale du Cercle',
                'position' => 0, 'archived' => false, 'isDefault' => true, 'createdBy' => null, 'createdAt' => self::MS,
            ],
            $this->views()->room($room),
        );
    }

    public function testRoomWithCreatorAndDeletedCreator(): void
    {
        $t = Millis::toDateTime(self::MS);
        $views = $this->views();
        $mine = $views->room(new Room('r1', 'Analyses', '', 1, true, self::ALICE, $t));
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $mine['createdBy']);
        self::assertTrue($mine['archived']);
        self::assertFalse($mine['isDefault']);
        $gone = $views->room(new Room('r2', 'Vieux', '', 2, false, self::BOB, $t));
        self::assertSame(['id' => self::BOB, 'pseudo' => '(compte supprimé)'], $gone['createdBy']);
    }

    public function testRoomsResolveCreatorsWithOneFindMany(): void
    {
        $spy = $this->spy();
        $views = new MessageViews($spy);
        $t = Millis::toDateTime(self::MS);
        $out = $views->rooms([
            new Room('general', 'Général', '', 0, false, null, $t),
            new Room('r1', 'A1', '', 1, false, self::ALICE, $t),
            new Room('r2', 'B2', '', 2, false, self::BOB, $t),
            new Room('r3', 'C3', '', 3, false, self::ALICE, $t),
        ]);

        self::assertSame(1, $spy->findManyCalls);
        self::assertNull($out[0]['createdBy']);
        self::assertSame('alice', $out[1]['createdBy']['pseudo']);
        self::assertSame('(compte supprimé)', $out[2]['createdBy']['pseudo']);
        self::assertSame('r3', $out[3]['id']);
    }
}
