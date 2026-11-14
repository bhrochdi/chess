<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\GameCommentService;
use LeCercle\Chat\MessageViews;
use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Millis;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryGameCommentRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\KnownGames;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameCommentServiceTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const CARL = 'u000000000004';
    private const DORA = 'u000000000005';
    private const GAME = 'g0123456789ab';
    private const OTHER_GAME = 'g0123456789ac';

    private InMemoryGameCommentRepository $comments;
    private CountingMembers $members;
    private FakeSession $session;
    private GameCommentService $service;

    protected function setUp(): void
    {
        $this->comments = new InMemoryGameCommentRepository();
        $this->members = new CountingMembers(new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::suspended(self::BOB, 'bob'),
            MemberFactory::user(self::CARL, 'carl'),
            MemberFactory::user(self::DORA, 'dora'),
        ));
        $this->session = new FakeSession();
        $this->service = new GameCommentService(
            $this->comments,
            new KnownGames(self::GAME, 'g0123456789ac'),
            new Access($this->session, $this->members),
            new FixedClock(Millis::toDateTime(1794477600123)),
            new MessageViews($this->members),
        );
    }

    public function testAnonymousCanListAnEmptyThread(): void
    {
        self::assertSame([], $this->service->list(self::GAME));
    }

    #[DataProvider('badIds')]
    public function testMalformedGameIdIsNotFound(string $id): void
    {
        $this->expectError(NotFoundException::class, 'Partie introuvable.', fn() => $this->service->list($id));
        $this->expectError(NotFoundException::class, 'Partie introuvable.', fn() => $this->service->send($id, 'x'));
    }

    public function testUnknownGameIsNotFound(): void
    {
        $this->session->logIn(self::ALICE);

        $this->expectError(NotFoundException::class, 'Partie introuvable.', fn() => $this->service->list('g00000000dead'));
        $this->expectError(NotFoundException::class, 'Partie introuvable.', fn() => $this->service->send('g00000000dead', 'x'));
    }

    /** @return array<string, array{string}> */
    public static function badIds(): array
    {
        return [
            'prefix' => ['game:1'],
            'uppercase' => ['G0123456789AB'],
            'too long' => ['g0123456789abc'],
            'too short' => ['g0123456789a'],
            'trailing newline' => ["g0123456789ab\n"],
            'empty' => [''],
        ];
    }

    public function testMemberSendsTrimmedCommentThenItIsListed(): void
    {
        $this->session->logIn(self::ALICE);

        $sent = $this->service->send(self::GAME, ' Joli coup ! ');

        self::assertSame('Joli coup !', $sent['text']);
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $sent['author']);
        self::assertSame(1794477600123, $sent['createdAt']);
        $this->session->logOut();
        self::assertSame([$sent], $this->service->list(self::GAME));
    }

    public function testThreadsAreSeparatedPerGame(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->send(self::GAME, 'ici');

        self::assertSame([], $this->service->list(self::OTHER_GAME));
    }

    public function testListKeepsTheEightyNewestOldestFirst(): void
    {
        $this->session->logIn(self::ALICE);
        for ($i = 1; $i <= 85; ++$i) {
            $this->service->send(self::GAME, "c$i");
        }

        $texts = array_column($this->service->list(self::GAME), 'text');

        self::assertCount(80, $texts);
        self::assertSame('c6', $texts[0]);
        self::assertSame('c85', $texts[79]);
    }

    public function testListResolvesAuthorsWithASingleFindMany(): void
    {
        $t = Millis::toDateTime(1794477600123);
        foreach ([self::ALICE, self::BOB, self::CARL, self::DORA, self::ALICE] as $author) {
            $this->comments->add(self::GAME, $author, 'x', $t);
        }

        $view = $this->service->list(self::GAME);

        self::assertSame(['alice', 'bob', 'carl', 'dora', 'alice'], array_column(array_column($view, 'author'), 'pseudo'));
        self::assertSame(1, $this->members->findManyCalls);
    }

    public function testAnonymousCannotSend(): void
    {
        $this->expectError(ForbiddenException::class, 'Connexion requise.', fn() => $this->service->send(self::GAME, 'x'));
    }

    public function testSuspendedCannotSend(): void
    {
        $this->session->logIn(self::BOB);
        $this->expectError(ForbiddenException::class, 'Compte suspendu par l’administration.', fn() => $this->service->send(self::GAME, 'x'));
        self::assertSame([], $this->comments->latest(self::GAME, 10));
    }

    public function testEmptyTextIsRefusedAndNothingStored(): void
    {
        $this->session->logIn(self::ALICE);
        $this->expectError(ValidationException::class, 'Message vide.', fn() => $this->service->send(self::GAME, '  '));
        self::assertSame([], $this->comments->latest(self::GAME, 10));
    }

    /** @param class-string<\Throwable> $class */
    private function expectError(string $class, string $message, callable $call): void
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
