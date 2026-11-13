<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\PieceSquareEvaluator;
use LeCercle\Chess\Ai\PieceValues;
use LeCercle\Chess\PieceType;
use PHPUnit\Framework\TestCase;

final class PieceValuesTest extends TestCase
{
    public function testValues(): void
    {
        self::assertSame(
            [100, 320, 330, 500, 900, 20000],
            array_map(PieceValues::of(...), PieceType::cases()),
        );
    }

    public function testEvaluatorValueDelegatesToPieceValues(): void
    {
        foreach (PieceType::cases() as $type) {
            self::assertSame(PieceValues::of($type), PieceSquareEvaluator::value($type));
        }
    }
}
