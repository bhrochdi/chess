<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Shared\NotFoundException;
use PHPUnit\Framework\TestCase;

/** Relecture périodique d'une partie : « rien n'a changé » sans recharger ni rejouer la partie. */
final class GamePollingTest extends TestCase
{
    use GameServiceFixture;

    private GameService $service;

    protected function setUp(): void
    {
        $this->setUpFixture();
        $this->service = $this->service();
    }

    /** Version que le navigateur affiche après une lecture entière. */
    private function version(string $id): string
    {
        $version = $this->service->get($id)['version'];
        self::assertIsString($version);

        return $version;
    }

    public function testAnUnchangedGameAnswersNullWithoutAFullRead(): void
    {
        $id = $this->onlineGame($this->service);
        $known = $this->version($id);
        $this->games->fullReads = 0;
        $this->games->pulseReads = 0;

        self::assertNull($this->service->getIfChanged($id, $known));

        self::assertSame([0, 1], [$this->games->fullReads, $this->games->pulseReads]);
    }

    public function testASpectatorWithoutAccountGetsTheSameAnswer(): void
    {
        $id = $this->onlineGame($this->service);
        $known = $this->version($id);
        $this->as(null);

        self::assertNull($this->service->getIfChanged($id, $known));
    }

    public function testAMoveChangesTheVersion(): void
    {
        $id = $this->onlineGame($this->service);
        $known = $this->version($id);
        $this->clock->advanceMs(1500);
        $this->service->move($id, 'e2e4', 0);

        $view = $this->service->getIfChanged($id, $known);

        self::assertNotNull($view);
        self::assertSame(['e4'], $view['moves']);
        self::assertNotSame($known, $view['version']);
        self::assertNull($this->service->getIfChanged($id, (string) $view['version']));
    }

    public function testEveryWriteAtTheSameInstantChangesTheVersion(): void
    {
        $this->as(self::ALICE);
        $id = $this->service->create('online', null, null, 'w')['id'];
        $seen = [$this->version($id)];

        $this->as(self::BOB);
        $this->service->join($id);
        $seen[] = $this->version($id);
        $this->as(self::ALICE);
        $this->service->move($id, 'e2e4', 0);
        $seen[] = $this->version($id);
        $this->service->offerDraw($id);
        $seen[] = $this->version($id);
        $this->as(self::BOB);
        $this->service->acceptDraw($id);
        $seen[] = $this->version($id);

        self::assertCount(5, array_unique($seen));
    }

    public function testAnUndoChangesTheVersion(): void
    {
        $this->as(self::ALICE);
        $id = $this->service->create('local', null, null, null)['id'];
        $this->service->move($id, 'e2e4', 0);
        $known = $this->version($id);

        $this->service->undo($id);

        self::assertSame([], $this->service->getIfChanged($id, $known)['moves'] ?? null);
    }

    public function testAnUnknownVersionAnswersWithTheGame(): void
    {
        $id = $this->onlineGame($this->service);

        self::assertSame($id, $this->service->getIfChanged($id, 'autre')['id'] ?? null);
    }

    public function testARunningClockAloneDoesNotChangeTheGame(): void
    {
        $id = $this->onlineGame($this->service, ['base' => 60, 'inc' => 0]);
        $this->service->move($id, 'e2e4', 0);
        $known = $this->version($id);

        $this->clock->advanceMs(59_999);

        self::assertNull($this->service->getIfChanged($id, $known));
    }

    public function testAFallenFlagForcesAFullReadThatEndsTheGame(): void
    {
        $id = $this->onlineGame($this->service, ['base' => 60, 'inc' => 0]);
        $this->service->move($id, 'e2e4', 0);
        $known = $this->version($id);

        $this->clock->advanceMs(60_000);
        $view = $this->service->getIfChanged($id, $known);

        self::assertNotNull($view);
        self::assertSame(['finished', '1-0', 'temps'], [$view['status'], $view['result'], $view['reason']]);
        self::assertNull($this->service->getIfChanged($id, (string) $view['version']));
    }

    public function testALocalGameStaysHiddenFromOthers(): void
    {
        $this->as(self::ALICE);
        $id = $this->service->create('local', null, null, null)['id'];
        $known = $this->version($id);
        self::assertNull($this->service->getIfChanged($id, $known));

        $this->as(self::BOB);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        $this->service->getIfChanged($id, $known);
    }

    public function testAnUnknownGameIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        $this->service->getIfChanged('g000000000000', 'x');
    }
}
