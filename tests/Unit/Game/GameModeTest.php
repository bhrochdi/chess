<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameMode;
use LeCercle\Game\GameStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameModeTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function unknownModes(): iterable
    {
        yield 'échecs' => ['chess'];
    }

    public function testComputerModeIsStoredAsAi(): void
    {
        self::assertSame(GameMode::Computer, GameMode::tryFrom('ai'));
    }

    #[DataProvider('unknownModes')]
    public function testUnknownModeIsNull(string $stored): void
    {
        self::assertNull(GameMode::tryFrom($stored));
    }

    public function testStatusFromValue(): void
    {
        self::assertSame(GameStatus::Open, GameStatus::from('open'));
    }
}
