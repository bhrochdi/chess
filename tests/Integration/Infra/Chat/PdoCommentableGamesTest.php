<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Chess\Color;
use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Infra\Chat\PdoCommentableGames;
use LeCercle\Infra\Game\PdoGameRepository;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoCommentableGamesTest extends DatabaseTestCase
{
    public function testExistsOnlyForOnlineGames(): void
    {
        $now = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
        $games = new PdoGameRepository(self::pdo(), ChessRules::standard());
        $games->add(Game::online('g0000000000c1', 'alice', Color::White, null, $now));
        $games->add(Game::local('g0000000000c2', 'alice', null, $now));
        $commentable = new PdoCommentableGames(self::pdo());

        self::assertTrue($commentable->exists('g0000000000c1'));
        self::assertFalse($commentable->exists('g0000000000c2'));
        self::assertFalse($commentable->exists('g0000000000c3'));
    }
}
