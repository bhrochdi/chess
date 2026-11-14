<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\EndReason;
use LeCercle\Game\FinishReason;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FinishReasonTest extends TestCase
{
    /** @return iterable<string, array{EndReason, FinishReason}> */
    public static function endReasons(): iterable
    {
        yield 'mat' => [EndReason::Checkmate, FinishReason::Checkmate];
        yield 'pat' => [EndReason::Stalemate, FinishReason::Stalemate];
        yield '50 coups' => [EndReason::FiftyMoves, FinishReason::FiftyMoves];
        yield 'répétition' => [EndReason::Threefold, FinishReason::Threefold];
        yield 'matériel' => [EndReason::InsufficientMaterial, FinishReason::InsufficientMaterial];
    }

    #[DataProvider('endReasons')]
    public function testEngineReasonMapsToSameValue(EndReason $engine, FinishReason $expected): void
    {
        self::assertSame($expected, FinishReason::fromEndReason($engine));
        self::assertSame($engine->value, $expected->value);
    }

    public function testEveryEngineReasonIsCovered(): void
    {
        foreach (EndReason::cases() as $case) {
            self::assertSame($case->value, FinishReason::fromEndReason($case)->value);
        }
    }
}
