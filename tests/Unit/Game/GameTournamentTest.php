<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Result;
use LeCercle\Game\GameService;
use LeCercle\Shared\TimeControl;
use LeCercle\Tests\Support\RecordingGameEndListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameTournamentTest extends TestCase
{
    use GameServiceFixture;

    private RecordingGameEndListener $listener;

    protected function setUp(): void
    {
        $this->setUpFixture();
        $this->listener = new RecordingGameEndListener();
    }

    public function testTournamentGameStartsPlayingWithBothSeatsAndAStoppedClock(): void
    {
        $service = $this->service(listener: $this->listener);
        $id = $service->createForPairing(self::ALICE, self::BOB, new TimeControl(60, 0));
        $this->as(self::BOB);

        $view = $service->get($id);

        self::assertSame(['online', 'playing'], [$view['mode'], $view['status']]);
        self::assertSame([self::ALICE, self::BOB], [$view['white']['id'], $view['black']['id']]);
        self::assertSame([60000, 60000, null], [$view['clock']['whiteMs'], $view['clock']['blackMs'], $view['clock']['running']]);
    }

    public function testClockStartsOnWhitesFirstMove(): void
    {
        $service = $this->service(listener: $this->listener);
        $id = $service->createForPairing(self::ALICE, self::BOB, new TimeControl(60, 0));
        $this->clock->advanceMs(120_000);
        $this->as(self::ALICE);

        $view = $service->move($id, 'e2e4', 0);

        self::assertSame(['playing', 'b', 60000], [$view['status'], $view['clock']['running'], $view['clock']['whiteMs']]);
    }

    /** @return iterable<string, array{\Closure(self, GameService, string): void, Result}> */
    public static function endings(): iterable
    {
        yield 'mat' => [static function (self $t, GameService $s, string $id): void {
            foreach (['f2f3', 'e7e5', 'g2g4', 'd8h4'] as $ply => $uci) {
                $t->as($ply % 2 === 0 ? self::ALICE : self::BOB);
                $s->move($id, $uci, $ply);
            }
        }, Result::BlackWins];
        yield 'abandon' => [static function (self $t, GameService $s, string $id): void {
            $t->as(self::ALICE);
            $s->resign($id);
        }, Result::BlackWins];
        yield 'accord' => [static function (self $t, GameService $s, string $id): void {
            $t->as(self::ALICE);
            $s->offerDraw($id);
            $t->as(self::BOB);
            $s->acceptDraw($id);
        }, Result::Draw];
        yield 'temps' => [static function (self $t, GameService $s, string $id): void {
            $t->as(self::ALICE);
            $s->move($id, 'e2e4', 0);
            $t->clock->advanceMs(61_000);
            $s->get($id);
            $s->get($id);
        }, Result::WhiteWins];
        yield 'admin' => [static function (self $t, GameService $s, string $id): void {
            $t->as(self::ROOT);
            $s->adminClose($id, '1-0');
        }, Result::WhiteWins];
    }

    /** @param \Closure(self, GameService, string): void $finish */
    #[DataProvider('endings')]
    public function testEveryFinishNotifiesTheListenerOnce(\Closure $finish, Result $expected): void
    {
        $service = $this->service(listener: $this->listener);
        $id = $service->createForPairing(self::ALICE, self::BOB, new TimeControl(60, 0));

        $finish($this, $service, $id);

        self::assertEquals([[$id, $expected]], $this->listener->finished);
    }

    public function testUndoDoesNotNotify(): void
    {
        $service = $this->service(listener: $this->listener);
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        foreach (['f2f3', 'e7e5', 'g2g4', 'd8h4'] as $ply => $uci) {
            $service->move($id, $uci, $ply);
        }
        $this->listener->finished = [];

        $service->undo($id);

        self::assertSame([], $this->listener->finished);
    }
}
