<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Game\GameStatus;
use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Tests\Support\InMemoryGameRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameServiceAccessTest extends TestCase
{
    use GameServiceFixture;

    protected function setUp(): void
    {
        $this->setUpFixture();
    }

    /** Appelle l'action $action du service sur la partie $id avec des paramètres valides. */
    private static function call(GameService $service, string $action, string $id): mixed
    {
        return match ($action) {
            'create' => $service->create('local', null, null, null),
            'join' => $service->join($id),
            'move' => $service->move($id, 'e2e4', 0),
            'resign' => $service->resign($id),
            'offerDraw' => $service->offerDraw($id),
            'acceptDraw' => $service->acceptDraw($id),
            'undo' => $service->undo($id),
            'mine' => $service->mine(),
            'get' => $service->get($id),
            'pgn' => $service->pgn($id),
            default => throw new \LogicException($action),
        };
    }

    /** @return iterable<string, array{string}> */
    public static function memberActions(): iterable
    {
        foreach (['create', 'join', 'move', 'resign', 'offerDraw', 'acceptDraw', 'undo', 'mine'] as $action) {
            yield $action => [$action];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function writesOnAGame(): iterable
    {
        foreach (['join', 'move', 'resign', 'offerDraw', 'acceptDraw', 'undo'] as $action) {
            yield $action => [$action];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function playerActions(): iterable
    {
        foreach (['move', 'resign', 'offerDraw', 'acceptDraw'] as $action) {
            yield $action => [$action];
        }
    }

    #[DataProvider('memberActions')]
    public function testAnonymousIsRefused(string $action): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(null);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage(Access::LOGIN_REQUIRED);
        self::call($service, $action, $id);
    }

    #[DataProvider('memberActions')]
    public function testSuspendedMemberIsRefused(string $action): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::SUSPENDED);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Compte suspendu par l’administration.');
        self::call($service, $action, $id);
    }

    #[DataProvider('writesOnAGame')]
    public function testWritesLockTheGameInsideOneTransaction(string $action): void
    {
        $games = $this->games;
        $runner = new class ($games) implements TransactionRunner {
            public int $runs = 0;
            public int $lockedInside = 0;

            public function __construct(private readonly InMemoryGameRepository $games) {}

            public function run(callable $work): mixed
            {
                ++$this->runs;
                $before = \count($this->games->lockedIds);
                $result = $work();
                $this->lockedInside += \count($this->games->lockedIds) - $before;

                return $result;
            }
        };
        $service = $this->service(transactions: $runner);
        $this->as(self::ALICE);
        $id = $action === 'join' || $action === 'undo'
            ? $service->create($action === 'join' ? 'online' : 'local', null, null, 'w')['id']
            : $this->onlineGame($service);
        if ($action === 'undo') {
            $service->move($id, 'e2e4', 0);
        }
        if ($action === 'acceptDraw') {
            $service->offerDraw($id);
            $this->as(self::BOB);
        }
        if ($action === 'join') {
            $this->as(self::BOB);
        }
        $runner->runs = 0;
        $runner->lockedInside = 0;
        $games->lockedIds = [];

        self::call($service, $action, $id);

        self::assertSame(1, $runner->runs, 'une transaction');
        self::assertSame(1, $runner->lockedInside, 'partie verrouillée dans la transaction');
        self::assertSame([$id], $games->lockedIds);
    }

    public function testGetWritesAFallenFlagUnderLock(): void
    {
        $games = $this->games;
        $runner = new class ($games) implements TransactionRunner {
            public int $runs = 0;
            public int $lockedInside = 0;

            public function __construct(private readonly InMemoryGameRepository $games) {}

            public function run(callable $work): mixed
            {
                ++$this->runs;
                $before = \count($this->games->lockedIds);
                $result = $work();
                $this->lockedInside += \count($this->games->lockedIds) - $before;

                return $result;
            }
        };
        $service = $this->service(transactions: $runner);
        $id = $this->onlineGame($service);
        $runner->runs = 0;
        $runner->lockedInside = 0;
        $games->lockedIds = [];

        $service->get($id);
        self::assertSame(0, $runner->runs, 'pendule intacte : simple lecture');

        $this->clock->advanceMs(301_000);
        $view = $service->get($id);

        self::assertSame(1, $runner->runs, 'une transaction');
        self::assertSame(1, $runner->lockedInside, 'partie verrouillée dans la transaction');
        self::assertSame('finished', $view['status']);
        self::assertSame('finished', $this->games->get($id)->snapshot()->status->value);
    }

    public function testCreateRunsInATransaction(): void
    {
        $runner = new class implements TransactionRunner {
            public int $runs = 0;

            public function run(callable $work): mixed
            {
                ++$this->runs;

                return $work();
            }
        };
        $this->as(self::ALICE);

        $this->service(transactions: $runner)->create('local', null, null, null);

        self::assertSame(1, $runner->runs);
    }

    public function testAnonymousRefusalTouchesNothing(): void
    {
        $runner = new class implements TransactionRunner {
            public int $runs = 0;

            public function run(callable $work): mixed
            {
                ++$this->runs;

                return $work();
            }
        };
        $service = $this->service(transactions: $runner);
        $id = $this->onlineGame($service);
        $runner->runs = 0;
        $this->as(null);

        try {
            $service->move($id, 'e2e4', 0);
            self::fail('ForbiddenException attendue');
        } catch (ForbiddenException) {
        }
        self::assertSame(0, $runner->runs);
    }

    /** @return iterable<string, array{string, string}> */
    public static function privateGameActions(): iterable
    {
        foreach (['local', 'ai'] as $mode) {
            foreach (['get', 'pgn', 'join', 'move', 'undo', 'resign'] as $action) {
                yield "$mode $action" => [$mode, $action];
            }
        }
    }

    #[DataProvider('privateGameActions')]
    public function testSomeoneElsesPrivateGameIsNotFound(string $mode, string $action): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create($mode, 'ai-2', null, null)['id'];
        $this->as(self::BOB);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        self::call($service, $action, $id);
    }

    public function testAnonymousCannotReadAPrivateGame(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        $this->as(null);

        $this->expectException(NotFoundException::class);
        $service->get($id);
    }

    public function testOwnerReadsTheirPrivateGame(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('ai', 'ai-2', null, null)['id'];

        self::assertSame('w', $service->get($id)['yourColor']);
        self::assertSame('le-cercle-' . $id . '.pgn', $service->pgn($id)['filename']);
    }

    public function testAnonymousReadsAnOnlineGame(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(null);

        $view = $service->get($id);

        self::assertSame($id, $view['id']);
        self::assertNull($view['yourColor']);
        self::assertSame('le-cercle-' . $id . '.pgn', $service->pgn($id)['filename']);
    }

    public function testSuspendedMemberStillReads(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::SUSPENDED);

        self::assertSame($id, $service->get($id)['id']);
    }

    #[DataProvider('playerActions')]
    public function testAStrangerCannotActInAGame(string $action): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        if ($action === 'acceptDraw') {
            $service->offerDraw($id);
        }
        $this->as(self::CAROL);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Tu ne joues pas cette partie.');
        self::call($service, $action, $id);
    }

    #[DataProvider('playerActions')]
    public function testAStrangerCannotFinishAGameOnTime(string $action): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service, ['base' => 60, 'inc' => 0]);
        $this->clock->advanceMs(61000);
        $this->as(self::CAROL);

        try {
            self::call($service, $action, $id);
            self::fail('ForbiddenException attendue');
        } catch (ForbiddenException $e) {
            self::assertSame('Tu ne joues pas cette partie.', $e->getMessage());
        }
        self::assertSame(GameStatus::Playing, $this->games->get($id)->status());
    }

    #[DataProvider('playerActions')]
    public function testUnknownGameIsNotFoundForWrites(string $action): void
    {
        $this->as(self::ALICE);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        self::call($this->service(), $action, 'g999999999999');
    }

    public function testUnknownGameIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        $this->service()->get('g999999999999');
    }

    public function testAnonymousReadsTheLobby(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(null);

        self::assertSame([$id], array_column($service->lobby(), 'id'));
    }
}
