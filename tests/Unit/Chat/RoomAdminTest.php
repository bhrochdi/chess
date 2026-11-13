<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\MessageViews;
use LeCercle\Chat\Room;
use LeCercle\Chat\RoomService;
use LeCercle\Shared\Access;
use LeCercle\Shared\Member;
use LeCercle\Shared\Role;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Millis;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\InMemoryRoomMessageRepository;
use LeCercle\Tests\Support\InMemoryRoomRepository;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\Tests\Support\RecordingAdminJournal;
use LeCercle\Tests\Support\TrackingTransactionRunner;
use LeCercle\Tests\Support\TransactionAwareAdminJournal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoomAdminTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const ROOT = 'u000000000009';
    private const ANALYSES = 'r000000000001';
    private const ARCHIVES = 'r000000000002';

    private \DateTimeImmutable $t0;
    private InMemoryRoomMessageRepository $messages;
    private RecordingAdminJournal $journal;
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
        $members = new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::admin(self::ROOT, 'root'),
            MemberFactory::suspended(self::BOB, 'bob'),
        );
        $this->journal = new RecordingAdminJournal();
        $this->session = new FakeSession(self::ROOT);
        $this->service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $members),
            new FixedClock($this->t0),
            new MessageViews($members),
            $this->journal,
            new ImmediateTransactionRunner(),
            $members,
        );
    }

    public function testCreate(): void
    {
        $out = $this->service->create(' Débutants ', 'Pour apprendre');

        self::assertSame('Débutants', $out['name']);
        self::assertSame('Pour apprendre', $out['description']);
        self::assertSame(3, $out['position']);
        self::assertFalse($out['archived']);
        self::assertSame(['id' => self::ROOT, 'pseudo' => 'root'], $out['createdBy']);
        self::assertSame([['actor' => self::ROOT, 'action' => 'Création du salon « Débutants »']], $this->journal->entries);
    }

    public function testCreateRefusesATakenNameCaseInsensitively(): void
    {
        $this->expectError(ConflictException::class, 'Ce nom de salon est déjà pris.', fn() => $this->service->create('analyses', ''));
        self::assertSame([], $this->journal->entries);
    }

    public function testRenameKeepsTheIdAndChecksOtherNames(): void
    {
        $same = $this->service->rename(self::ANALYSES, 'Analyses');
        self::assertSame('Analyses', $same['name']);
        self::assertSame([], $this->journal->entries);

        $this->expectError(ConflictException::class, 'Ce nom de salon est déjà pris.', fn() => $this->service->rename(self::ANALYSES, 'GÉNÉRAL'));
        self::assertSame([], $this->journal->entries);

        $out = $this->service->rename(self::ANALYSES, 'Parties commentées');
        self::assertSame(self::ANALYSES, $out['id']);
        self::assertSame('Parties commentées', $out['name']);
        self::assertSame(
            [['actor' => self::ROOT, 'action' => 'Salon « Analyses » renommé « Parties commentées »']],
            $this->journal->entries,
        );
    }

    public function testRenameUnknownRoom(): void
    {
        $this->expectError(NotFoundException::class, 'Salon introuvable.', fn() => $this->service->rename('rnope', 'Autre nom'));
    }

    public function testMoveSwapsWithTheNeighbour(): void
    {
        $out = $this->service->move(self::ANALYSES, 'up');

        self::assertSame([self::ANALYSES, 'general', self::ARCHIVES], array_column($out, 'id'));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Salon « Analyses » déplacé']], $this->journal->entries);
    }

    public function testMoveAtTheEdgeChangesNothing(): void
    {
        $out = $this->service->move('general', 'up');
        self::assertSame(['general', self::ANALYSES, self::ARCHIVES], array_column($out, 'id'));

        $out = $this->service->move(self::ARCHIVES, 'down');
        self::assertSame(['general', self::ANALYSES, self::ARCHIVES], array_column($out, 'id'));
        self::assertSame([], $this->journal->entries);
    }

    public function testMoveRejectsAnUnknownDirection(): void
    {
        $this->expectError(ValidationException::class, 'Direction inconnue.', fn() => $this->service->move('general', 'left'));
    }

    public function testArchiveAndUnarchive(): void
    {
        $out = $this->service->setArchived(self::ANALYSES, true);
        self::assertTrue($out['archived']);

        $out = $this->service->setArchived(self::ANALYSES, false);
        self::assertFalse($out['archived']);

        self::assertSame(
            [
                ['actor' => self::ROOT, 'action' => 'Archivage du salon « Analyses »'],
                ['actor' => self::ROOT, 'action' => 'Désarchivage du salon « Analyses »'],
            ],
            $this->journal->entries,
        );
    }

    public function testArchiveWithoutEffectWritesNothing(): void
    {
        $this->service->setArchived('general', false);
        $this->service->setArchived(self::ARCHIVES, true);
        $this->service->setArchived(self::ANALYSES, false);
        self::assertSame([], $this->journal->entries);

        $this->service->setArchived(self::ANALYSES, true);
        $this->service->setArchived(self::ANALYSES, true);
        self::assertSame(
            [['actor' => self::ROOT, 'action' => 'Archivage du salon « Analyses »']],
            $this->journal->entries,
        );
    }

    public function testMoveOfAnUnknownRoomIsNotFound(): void
    {
        $this->expectError(NotFoundException::class, 'Salon introuvable.', fn() => $this->service->move('rnope', 'up'));
        self::assertSame([], $this->journal->entries);
    }

    public function testMoveRenumbersRoomsSharingAPosition(): void
    {
        $rooms = new InMemoryRoomRepository();
        $rooms->add(new Room('general', 'Général', '', 0, false, null, $this->t0));
        $rooms->add(new Room('ra', 'Alpha', '', 1, false, self::ROOT, $this->t0));
        $rooms->add(new Room('rb', 'Beta', '', 1, false, self::ROOT, $this->t0));
        $members = new InMemoryMembers(MemberFactory::admin(self::ROOT, 'root'));
        $service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $members),
            new FixedClock($this->t0),
            new MessageViews($members),
            $this->journal,
            new ImmediateTransactionRunner(),
            $members,
        );

        $out = $service->move('rb', 'up');

        self::assertSame(['general', 'rb', 'ra'], array_column($out, 'id'));
        self::assertSame([0, 1, 2], array_column($out, 'position'));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Salon « Beta » déplacé']], $this->journal->entries);
    }

    public function testGeneralIsProtected(): void
    {
        $this->expectError(ConflictException::class, 'Le salon « Général » reste toujours visible.', fn() => $this->service->setArchived('general', true));
        $this->expectError(ConflictException::class, 'Le salon « Général » ne peut pas être supprimé.', fn() => $this->service->delete('general'));
        self::assertSame([], $this->journal->entries);
    }

    public function testDelete(): void
    {
        $this->service->delete(self::ANALYSES);

        self::assertCount(2, $this->service->listAll());
        self::assertSame([['actor' => self::ROOT, 'action' => 'Suppression du salon « Analyses »']], $this->journal->entries);
        $this->expectError(NotFoundException::class, 'Salon introuvable.', fn() => $this->service->delete(self::ANALYSES));
    }

    public function testDeleteMessage(): void
    {
        for ($i = 1; $i <= 6; ++$i) {
            $this->messages->add('general', self::ROOT, "m$i", $this->t0);
        }
        $this->messages->add('general', self::ALICE, 'à effacer', $this->t0);

        $this->service->deleteMessage('general', 7);

        self::assertNull($this->messages->find('general', 7));
        self::assertSame(
            [['actor' => self::ROOT, 'action' => 'Suppression d’un message du salon « Général » (alice)']],
            $this->journal->entries,
        );
        $this->expectError(NotFoundException::class, 'Message introuvable.', fn() => $this->service->deleteMessage('general', 999));
    }

    public function testDeleteMessageOfADeletedAuthorNamesTheDeletedAccount(): void
    {
        $this->messages->add('general', 'u0000000000ff', 'orphelin', $this->t0);

        $this->service->deleteMessage('general', 1);

        self::assertSame('Suppression d’un message du salon « Général » (compte supprimé)', $this->journal->entries[0]['action']);
    }

    public function testClear(): void
    {
        $this->messages->add('general', self::ALICE, 'un', $this->t0);

        $this->service->clear('general');

        self::assertSame([], $this->service->messages('general'));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Salon « Général » vidé']], $this->journal->entries);
    }

    /** @return iterable<string, array{string}> */
    public static function actions(): iterable
    {
        foreach (['create', 'rename', 'move', 'setArchived', 'delete', 'deleteMessage', 'clear'] as $action) {
            yield $action => [$action];
        }
    }

    #[DataProvider('actions')]
    public function testEveryAdminActionRefusesNonAdmins(string $action): void
    {
        $this->session->logIn(self::ALICE);
        $this->expectError(ForbiddenException::class, 'Action réservée aux administrateurs.', fn() => $this->perform($action));

        $this->session->logOut();
        $this->expectError(ForbiddenException::class, 'Connexion requise.', fn() => $this->perform($action));

        self::assertSame([], $this->journal->entries);
    }

    #[DataProvider('actions')]
    public function testEveryAdminActionWritesItsJournalEntryInsideTheTransaction(string $action): void
    {
        $members = new InMemoryMembers(MemberFactory::admin(self::ROOT, 'root'));
        $rooms = new InMemoryRoomRepository();
        $rooms->add(new Room('general', 'Général', '', 0, false, null, $this->t0));
        $rooms->add(new Room(self::ANALYSES, 'Analyses', '', 1, false, self::ROOT, $this->t0));
        $this->messages->add('general', self::ROOT, 'un', $this->t0);
        $runner = new TrackingTransactionRunner();
        $journal = new TransactionAwareAdminJournal($runner);
        $service = new RoomService(
            $rooms,
            $this->messages,
            new Access($this->session, $members),
            new FixedClock($this->t0),
            new MessageViews($members),
            $journal,
            $runner,
            $members,
        );

        $this->perform($action, $service);

        self::assertCount(1, $journal->entries);
        self::assertTrue($journal->entries[0]['inside'], $action);
    }

    public function testASuspendedAdminIsRefused(): void
    {
        $members = new InMemoryMembers(new Member(self::ROOT, 'root', Role::Admin, true, false));
        $service = new RoomService(
            new InMemoryRoomRepository(),
            $this->messages,
            new Access($this->session, $members),
            new FixedClock($this->t0),
            new MessageViews($members),
            $this->journal,
            new ImmediateTransactionRunner(),
            $members,
        );

        $this->expectError(ForbiddenException::class, 'Compte suspendu par l’administration.', fn() => $service->create('Nouveau', ''));
        self::assertSame([], $this->journal->entries);
    }

    private function perform(string $action, ?RoomService $service = null): mixed
    {
        $service ??= $this->service;

        return match ($action) {
            'create' => $service->create('Nouveau', ''),
            'rename' => $service->rename(self::ANALYSES, 'Autre nom'),
            'move' => $service->move(self::ANALYSES, 'up'),
            'setArchived' => $service->setArchived(self::ANALYSES, true),
            'delete' => $service->delete(self::ANALYSES),
            'deleteMessage' => $service->deleteMessage('general', 1),
            'clear' => $service->clear('general'),
            default => throw new \LogicException("Action inconnue : $action"),
        };
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
