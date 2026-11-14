<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Admin;

use LeCercle\Admin\AdminService;
use LeCercle\Admin\DashboardCounts;
use LeCercle\Admin\JournalReader;
use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminServiceTest extends TestCase
{
    private const ROOT = 'u000000000009';
    private const ALICE = 'u000000000001';

    private FakeSession $session;
    private CountingMembers $members;
    private FixedClock $clock;
    /** @var list<\DateTimeImmutable> */
    private array $onlineSince = [];
    private AdminService $service;

    protected function setUp(): void
    {
        $this->session = new FakeSession(self::ROOT);
        $this->members = new CountingMembers(new InMemoryMembers(
            MemberFactory::admin(self::ROOT, 'root'),
            MemberFactory::user(self::ALICE, 'alice'),
        ));
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC')));
        $at = fn(int $s): \DateTimeImmutable => $this->clock->now()->modify("-{$s} seconds");
        $journal = new class ([
            ['actorId' => self::ROOT, 'action' => 'Création du salon « Analyses »', 'at' => $at(10)],
            ['actorId' => 'u0000000000dd', 'action' => 'Suspension de bob', 'at' => $at(20)],
            ['actorId' => self::ROOT, 'action' => 'Lancement du tournoi « Open » (4 joueurs)', 'at' => $at(30)],
        ]) implements JournalReader {
            /** @param list<array{actorId: string, action: string, at: \DateTimeImmutable}> $entries */
            public function __construct(private readonly array $entries) {}

            public function latest(int $limit): array
            {
                return \array_slice($this->entries, 0, $limit);
            }
        };
        $counts = new class ($this) implements DashboardCounts {
            public function __construct(private readonly AdminServiceTest $test) {}

            public function counts(\DateTimeImmutable $onlineSince): array
            {
                $this->test->sawOnlineSince($onlineSince);

                return ['accounts' => 5, 'online' => 2, 'open' => 1, 'playing' => 3, 'finished' => 7, 'roomMessages' => 42];
            }
        };
        $this->service = new AdminService(new Access($this->session, $this->members), $this->members, $this->clock, $journal, $counts);
    }

    public function sawOnlineSince(\DateTimeImmutable $since): void
    {
        $this->onlineSince[] = $since;
    }

    /** @return iterable<string, array{string}> */
    public static function actions(): iterable
    {
        yield 'journal' => ['journal'];
        yield 'dashboard' => ['dashboard'];
    }

    #[DataProvider('actions')]
    public function testNonAdminIsRefused(string $action): void
    {
        $this->session->logIn(self::ALICE);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Action réservée aux administrateurs.');
        $this->service->{$action}();
    }

    public function testJournalNewestFirstWithActorsResolvedOnce(): void
    {
        $this->members->findManyCalls = 0;

        $entries = $this->service->journal();

        self::assertSame(1, $this->members->findManyCalls);
        self::assertSame(
            [
                'actor' => ['id' => self::ROOT, 'pseudo' => 'root'],
                'action' => 'Création du salon « Analyses »',
                'at' => Millis::of($this->clock->now()->modify('-10 seconds')),
            ],
            $entries[0],
        );
        self::assertSame(['id' => 'u0000000000dd', 'pseudo' => '(compte supprimé)'], $entries[1]['actor']);
        self::assertCount(3, $entries);
    }

    public function testDashboardCountsPlayersSeenInTheLast70Seconds(): void
    {
        $counts = $this->service->dashboard();

        self::assertSame(['accounts' => 5, 'online' => 2, 'open' => 1, 'playing' => 3, 'finished' => 7, 'roomMessages' => 42], $counts);
        self::assertEquals([$this->clock->now()->modify('-70 seconds')], $this->onlineSince);
    }
}
