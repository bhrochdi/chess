<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\MessageViews;
use LeCercle\Chat\Room;
use LeCercle\Chat\RoomService;
use LeCercle\Shared\Access;
use LeCercle\Shared\Member;
use LeCercle\Shared\Role;
use LeCercle\Shared\ValidationException;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Millis;
use LeCercle\Shared\NotFoundException;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\InMemoryRoomMessageRepository;
use LeCercle\Tests\Support\InMemoryRoomRepository;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\Tests\Support\RecordingAdminJournal;
use PHPUnit\Framework\TestCase;

final class RoomServiceTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const ROOT = 'u000000000009';
    private const ANALYSES = 'r000000000001';
    private const ARCHIVES = 'r000000000002';

    private \DateTimeImmutable $t0;
    private InMemoryRoomMessageRepository $messages;
    private CountingMembers $members;
    private FakeSession $session;
    private RoomService $service;

    protected function setUp(): void
    {
        $this->t0 = Millis::toDateTime(1794477600123);
        $rooms = new InMemoryRoomRepository();
        $rooms->add(new Room('general', 'Général', '', 0, false, null, $this->t0));
        $rooms->add(new Room(self::ANALYSES, 'Analyses', '', 1, false, self::ROOT, $this->t0));
        $rooms->add(new Room(self::ARCHIVES, 'Archives', '', 2, true, self::ROOT, $this->t0));
        $this->messages = new InMemoryRoomMessageRepository();
        $this->members = new CountingMembers(new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::admin(self::ROOT, 'root'),
            MemberFactory::suspended(self::BOB, 'bob'),
        ));
        $this->session = new FakeSession();
        $this->service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $this->members),
            new FixedClock($this->t0),
            new MessageViews($this->members),
            new RecordingAdminJournal(),
            new ImmediateTransactionRunner(),
            $this->members,
        );
    }

    public function testListShowsNonArchivedRoomsInOrder(): void
    {
        self::assertSame(['general', self::ANALYSES], array_column($this->service->list(), 'id'));
        self::assertSame(1, $this->members->findManyCalls);
    }

    public function testListStaysAListWhenAnArchivedRoomIsInTheMiddle(): void
    {
        $rooms = new InMemoryRoomRepository();
        $rooms->add(new Room('general', 'Général', '', 0, false, null, $this->t0));
        $rooms->add(new Room(self::ARCHIVES, 'Archives', '', 1, true, self::ROOT, $this->t0));
        $rooms->add(new Room(self::ANALYSES, 'Analyses', '', 2, false, self::ROOT, $this->t0));
        $service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $this->members),
            new FixedClock($this->t0),
            new MessageViews($this->members),
            new RecordingAdminJournal(),
            new ImmediateTransactionRunner(),
            $this->members,
        );

        $out = $service->list();

        self::assertStringStartsWith('[', json_encode($out, JSON_THROW_ON_ERROR));
        self::assertSame(['general', self::ANALYSES], array_column($out, 'id'));
    }

    public function testListAllIsForAdmins(): void
    {
        $this->session->logIn(self::ALICE);
        try {
            $this->service->listAll();
            self::fail('Une exception était attendue.');
        } catch (ForbiddenException $e) {
            self::assertSame('Action réservée aux administrateurs.', $e->getMessage());
        }

        $this->session->logIn(self::ROOT);
        self::assertCount(3, $this->service->listAll());
    }

    public function testMessagesAreTheLastEighty(): void
    {
        for ($i = 1; $i <= 85; ++$i) {
            $this->messages->add('general', self::ALICE, "m$i", $this->t0);
        }
        $out = $this->service->messages('general');

        self::assertCount(80, $out);
        self::assertSame('m6', $out[0]['text']);
        self::assertSame('m85', $out[79]['text']);
        self::assertSame(1, $this->members->findManyCalls);
    }

    public function testMessagesOfAnArchivedRoomAreAdminOnly(): void
    {
        $this->expectNotFound(fn() => $this->service->messages(self::ARCHIVES));

        $this->session->logIn(self::ALICE);
        $this->expectNotFound(fn() => $this->service->messages(self::ARCHIVES));

        $this->session->logIn(self::ROOT);
        self::assertSame([], $this->service->messages(self::ARCHIVES));
    }

    public function testASuspendedAdminCannotReadAnArchivedRoom(): void
    {
        $members = new InMemoryMembers(new Member(self::ROOT, 'root', Role::Admin, true, false));
        $rooms = new InMemoryRoomRepository();
        $rooms->add(new Room(self::ARCHIVES, 'Archives', '', 0, true, null, $this->t0));
        $service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $members),
            new FixedClock($this->t0),
            new MessageViews($members),
            new RecordingAdminJournal(),
            new ImmediateTransactionRunner(),
            $members,
        );
        $this->session->logIn(self::ROOT);

        $this->expectNotFound(fn() => $service->messages(self::ARCHIVES));
    }

    public function testUnknownRoom(): void
    {
        $this->expectNotFound(fn() => $this->service->messages('rnope'));
        $this->session->logIn(self::ALICE);
        $this->expectNotFound(fn() => $this->service->send('rnope', 'Salut'));
    }

    public function testSendStoresTheCleanText(): void
    {
        $this->session->logIn(self::ALICE);
        $out = $this->service->send('general', '  Salut ');

        self::assertSame('Salut', $out['text']);
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $out['author']);
        self::assertSame(Millis::of($this->t0), $out['createdAt']);
        self::assertSame(1, $this->members->findManyCalls);
        self::assertSame(['Salut'], array_column($this->service->messages('general'), 'text'));
    }

    public function testSendRules(): void
    {
        $this->expectError(ForbiddenException::class, 'Connexion requise.', fn() => $this->service->send('general', 'Salut'));

        $this->session->logIn(self::BOB);
        $this->expectError(ForbiddenException::class, 'Compte suspendu par l’administration.', fn() => $this->service->send('general', 'Salut'));

        $this->session->logIn(self::ALICE);
        $this->expectError(ConflictException::class, 'Ce salon est archivé.', fn() => $this->service->send(self::ARCHIVES, 'Salut'));
        $this->expectError(ValidationException::class, 'Message vide.', fn() => $this->service->send('general', ''));
    }

    public function testSendRefusesMoreThanFiveHundredCharacters(): void
    {
        $this->session->logIn(self::ALICE);
        $this->expectError(ValidationException::class, 'Message trop long (500 caractères maximum).', fn() => $this->service->send('general', str_repeat('a', 501)));
    }

    public function testAnonymousCannotListAllRooms(): void
    {
        $this->expectError(ForbiddenException::class, 'Connexion requise.', fn() => $this->service->listAll());
    }

    private function expectNotFound(callable $call): void
    {
        $this->expectError(NotFoundException::class, 'Salon introuvable.', $call);
    }

    /** @param class-string<\Throwable> $class */
    private function expectError(string $class, string $message, callable $call): void
    {
        try {
            $call();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());

            return;
        }
        self::fail('Une exception était attendue.');
    }
}
