<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\DirectMessageService;
use LeCercle\Chat\GameCommentService;
use LeCercle\Chat\MessageRate;
use LeCercle\Chat\MessageViews;
use LeCercle\Chat\Room;
use LeCercle\Chat\RoomService;
use LeCercle\Shared\Access;
use LeCercle\Shared\Millis;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryDirectMessageRepository;
use LeCercle\Tests\Support\InMemoryGameCommentRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\InMemoryRoomMessageRepository;
use LeCercle\Tests\Support\InMemoryRoomRepository;
use LeCercle\Tests\Support\InMemoryThrottle;
use LeCercle\Tests\Support\KnownGames;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\Tests\Support\RecordingAdminJournal;
use PHPUnit\Framework\TestCase;

/** Débit des messages : une seule limite par compte pour les salons, les messages privés et les commentaires. */
final class MessageRateTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const GAME = 'g0123456789ab';

    private FixedClock $clock;
    private FakeSession $session;
    private InMemoryRoomMessageRepository $roomMessages;
    private RoomService $rooms;
    private DirectMessageService $direct;
    private GameCommentService $comments;

    protected function setUp(): void
    {
        $t0 = Millis::toDateTime(1794477600123);
        $this->clock = new FixedClock($t0);
        $this->session = new FakeSession(self::ALICE);
        $members = new InMemoryMembers(MemberFactory::user(self::ALICE, 'alice'), MemberFactory::user(self::BOB, 'bob'));
        $access = new Access($this->session, $members);
        $views = new MessageViews($members);
        $throttle = new InMemoryThrottle();
        $roomRepository = new InMemoryRoomRepository();
        $roomRepository->add(new Room('general', 'Général', '', 0, false, null, $t0));
        $this->roomMessages = new InMemoryRoomMessageRepository();
        $this->rooms = new RoomService(
            $roomRepository,
            $this->roomMessages,
            $access,
            $this->clock,
            $views,
            new RecordingAdminJournal(),
            new ImmediateTransactionRunner(),
            $members,
            $throttle,
        );
        $this->direct = new DirectMessageService(new InMemoryDirectMessageRepository(), $access, $members, $this->clock, $views, $throttle);
        $this->comments = new GameCommentService(
            new InMemoryGameCommentRepository(),
            new KnownGames(self::GAME),
            $access,
            $this->clock,
            $views,
            $throttle,
        );
    }

    private function fillTheWindow(): void
    {
        for ($i = 1; $i <= MessageRate::MAX; ++$i) {
            $this->rooms->send('general', "m$i");
        }
    }

    /** @param \Closure(): mixed $send */
    private function assertTooFast(\Closure $send): void
    {
        try {
            $send();
            self::fail('Le message devait être refusé.');
        } catch (TooManyRequestsException $e) {
            self::assertSame('Tu envoies des messages trop vite. Patiente quelques secondes.', $e->getMessage());
        }
    }

    public function testTheLimitIsEightMessagesInTwentySeconds(): void
    {
        self::assertSame([8, 20_000], [MessageRate::MAX, MessageRate::WINDOW_MS]);
    }

    public function testTheMessageBeyondTheLimitIsRefusedAndNotStored(): void
    {
        $this->fillTheWindow();

        $this->assertTooFast(fn() => $this->rooms->send('general', 'de trop'));

        self::assertCount(MessageRate::MAX, $this->rooms->messages('general'));
    }

    public function testTheLimitIsSharedByRoomsDirectMessagesAndComments(): void
    {
        $this->fillTheWindow();

        $this->assertTooFast(fn() => $this->direct->send(self::BOB, 'salut'));
        $this->assertTooFast(fn() => $this->comments->send(self::GAME, 'joli coup'));
    }

    public function testSendingResumesOnceTheWindowHasPassed(): void
    {
        $this->fillTheWindow();
        $this->clock->advanceMs(MessageRate::WINDOW_MS - 1);
        $this->assertTooFast(fn() => $this->rooms->send('general', 'trop tôt'));

        $this->clock->advanceMs(1);

        self::assertSame('à nouveau', $this->rooms->send('general', 'à nouveau')['text']);
    }

    public function testEachAccountHasItsOwnLimit(): void
    {
        $this->fillTheWindow();

        $this->session->logIn(self::BOB);

        self::assertSame('bonjour', $this->rooms->send('general', 'bonjour')['text']);
    }

    public function testARefusedMessageDoesNotCount(): void
    {
        for ($i = 0; $i < MessageRate::MAX + 3; ++$i) {
            try {
                $this->rooms->send('general', '   ');
                self::fail('Un message vide devait être refusé.');
            } catch (ValidationException) {
            }
        }

        $this->fillTheWindow();

        self::assertCount(MessageRate::MAX, $this->rooms->messages('general'));
    }
}
