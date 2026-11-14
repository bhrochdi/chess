<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\DirectMessageService;
use LeCercle\Chat\MessageViews;
use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryDirectMessageRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class DirectMessageServiceTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const CAROL = 'u000000000003';

    private InMemoryMembers $inner;
    private CountingMembers $members;
    private FakeSession $session;
    private InMemoryDirectMessageRepository $repo;
    private DirectMessageService $service;

    protected function setUp(): void
    {
        $this->inner = new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::user(self::BOB, 'bob'),
            MemberFactory::user(self::CAROL, 'carol'),
        );
        $this->members = new CountingMembers($this->inner);
        $this->session = new FakeSession();
        $this->repo = new InMemoryDirectMessageRepository();
        $this->service = $this->serviceWith($this->members);
    }

    public function testOpenFindsAPlayerByPseudo(): void
    {
        $this->session->logIn(self::ALICE);

        self::assertSame(['id' => self::BOB, 'pseudo' => 'bob'], $this->service->open('BOB'));
        $this->expectFailure(NotFoundException::class, 'Joueur introuvable.', fn() => $this->service->open('nobody'));
        $this->expectFailure(ValidationException::class, 'Tu ne peux pas t’écrire à toi-même.', fn() => $this->service->open('alice'));
    }

    public function testSendAndConversation(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->send(self::BOB, 'Salut');
        $this->session->logIn(self::BOB);
        $this->service->send(self::ALICE, 'Bonjour');

        $this->session->logIn(self::ALICE);
        self::assertSame(['Salut', 'Bonjour'], array_column($this->service->conversation(self::BOB), 'text'));
    }

    public function testConversationIsPrivate(): void
    {
        $this->session->logIn(self::CAROL);
        $this->service->send(self::BOB, 'carol-bob');
        $this->service->send(self::ALICE, 'carol-alice');
        $this->session->logIn(self::BOB);
        $this->service->send(self::CAROL, 'bob-carol');
        $this->service->send(self::ALICE, 'bob-alice');

        $this->session->logIn(self::ALICE);
        self::assertSame(['bob-alice'], array_column($this->service->conversation(self::BOB), 'text'));
    }

    public function testThreadsAndUnread(): void
    {
        $this->session->logIn(self::BOB);
        $this->service->send(self::ALICE, 'b1');
        $this->service->send(self::ALICE, 'b2');
        $this->session->logIn(self::CAROL);
        $this->service->send(self::ALICE, 'c1');

        $this->session->logIn(self::ALICE);
        $result = $this->service->threads();
        self::assertSame(3, $result['unreadTotal']);
        self::assertSame(self::CAROL, $result['threads'][0]['peer']['id']);
        self::assertFalse($result['threads'][0]['last']['fromMe']);

        $this->service->conversation(self::BOB);
        self::assertSame(1, $this->service->threads()['unreadTotal']);

        $this->session->logIn(self::BOB);
        $thread = $this->service->threads()['threads'][0];
        self::assertSame(self::ALICE, $thread['peer']['id']);
        self::assertSame(0, $thread['unread']);
        self::assertTrue($thread['last']['fromMe']);
    }

    public function testThreadsLimitShowsTwelveButCountsAllUnread(): void
    {
        for ($i = 1; $i <= 13; ++$i) {
            $this->inner->add(MemberFactory::user(sprintf('u%012d', 100 + $i), "peer$i"));
        }
        for ($i = 1; $i <= 13; ++$i) {
            $this->session->logIn(sprintf('u%012d', 100 + $i));
            $this->service->send(self::ALICE, 'salut');
        }

        $this->session->logIn(self::ALICE);
        $result = $this->service->threads();
        self::assertCount(DirectMessageService::THREADS_LIMIT, $result['threads']);
        $pseudos = array_column(array_column($result['threads'], 'peer'), 'pseudo');
        self::assertSame('peer13', $pseudos[0]);
        self::assertNotContains('peer1', $pseudos);
        self::assertSame(13, $result['unreadTotal']);
    }

    public function testDeletedPeerIsListedButCannotBeOpened(): void
    {
        $gone = 'u0000000000aa';
        $this->inner->add(MemberFactory::user($gone, 'gone'));
        $this->session->logIn($gone);
        $this->service->send(self::ALICE, 'adieu');

        $remaining = new InMemoryMembers(MemberFactory::user(self::ALICE, 'alice'));
        $service = $this->serviceWith($remaining);
        $this->session->logIn(self::ALICE);

        self::assertSame(MemberRef::DELETED_PSEUDO, $service->threads()['threads'][0]['peer']['pseudo']);
        $this->expectFailure(NotFoundException::class, 'Joueur introuvable.', fn() => $service->conversation($gone));
    }

    public function testRules(): void
    {
        $this->expectFailure(ForbiddenException::class, 'Connexion requise.', fn() => $this->service->threads());

        $this->session->logIn(self::ALICE);
        $this->expectFailure(ValidationException::class, 'Message vide.', fn() => $this->service->send(self::BOB, ''));
        $this->expectFailure(NotFoundException::class, 'Joueur introuvable.', fn() => $this->service->send('u0000000000ff', 'x'));
        $this->expectFailure(ValidationException::class, 'Tu ne peux pas t’écrire à toi-même.', fn() => $this->service->send(self::ALICE, 'x'));
    }

    public function testEachResponseReadsMembersOnce(): void
    {
        $this->session->logIn(self::BOB);
        $this->service->send(self::ALICE, 'b1');
        $this->session->logIn(self::CAROL);
        $this->service->send(self::ALICE, 'c1');
        $this->session->logIn(self::ALICE);
        $this->service->send(self::BOB, 'a1');
        $this->service->send(self::BOB, 'a2');
        $this->session->logIn(self::BOB);
        $this->service->send(self::ALICE, 'b2');
        $this->session->logIn(self::ALICE);

        $before = $this->members->findManyCalls;
        self::assertCount(2, $this->service->threads()['threads']);
        self::assertSame($before + 1, $this->members->findManyCalls);
        self::assertCount(4, $this->service->conversation(self::BOB));
        self::assertSame($before + 2, $this->members->findManyCalls);
        $this->service->send(self::BOB, 'ok');
        self::assertSame($before + 3, $this->members->findManyCalls);
    }

    public function testConversationWithOneselfIsRefused(): void
    {
        $this->session->logIn(self::ALICE);
        $this->expectFailure(ValidationException::class, 'Tu ne peux pas t’écrire à toi-même.', fn() => $this->service->conversation(self::ALICE));
    }

    private function serviceWith(Members $members): DirectMessageService
    {
        return new DirectMessageService(
            $this->repo,
            new Access($this->session, $members),
            $members,
            new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00')),
            new MessageViews($members),
        );
    }

    /** @param class-string<\Throwable> $class */
    private function expectFailure(string $class, string $message, callable $call): void
    {
        try {
            $call();
            self::fail('Une exception était attendue.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());
        }
    }
}
