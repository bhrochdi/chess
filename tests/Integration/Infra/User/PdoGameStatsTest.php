<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\User;

use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Infra\Game\PdoGameRepository;
use LeCercle\Infra\User\PdoGameStats;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoGameStatsTest extends DatabaseTestCase
{
    public function testForUsersCountsWinsLossesDraws(): void
    {
        $now = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
        $games = new PdoGameRepository(self::pdo(), ChessRules::standard());
        $finished = function (string $id, string $white, string $black, Result $result) use ($games, $now): void {
            $game = Game::online($id, $white, Color::White, null, $now);
            $game->join($black, $now);
            $game->closeByAdmin($result, $now);
            $games->add($game);
        };
        $finished('g0000000000a1', 'alice', 'bob', Result::WhiteWins);
        $finished('g0000000000a2', 'bob', 'alice', Result::WhiteWins);
        $finished('g0000000000a3', 'alice', 'carol', Result::Draw);
        $playing = Game::online('g0000000000a4', 'alice', Color::White, null, $now);
        $playing->join('bob', $now);
        $games->add($playing);
        $local = Game::local('g0000000000a5', 'alice', null, $now);
        $local->closeByAdmin(Result::WhiteWins, $now);
        $games->add($local);

        $stats = (new PdoGameStats(self::pdo()))->forUsers(['alice', 'bob', 'dave']);

        self::assertSame(['w' => 1, 'l' => 1, 'd' => 1], $stats['alice']->toArray());
        self::assertSame(['w' => 1, 'l' => 1, 'd' => 0], $stats['bob']->toArray());
        self::assertArrayNotHasKey('dave', $stats);
        self::assertArrayNotHasKey('carol', $stats);
        self::assertSame([], (new PdoGameStats(self::pdo()))->forUsers([]));
    }
}
