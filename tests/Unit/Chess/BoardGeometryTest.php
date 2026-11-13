<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\BoardGeometry;
use LeCercle\Chess\Square;
use PHPUnit\Framework\TestCase;

final class BoardGeometryTest extends TestCase
{
    /**
     * @param list<Square> $squares
     *
     * @return list<string>
     */
    private static function names(array $squares): array
    {
        return array_map(static fn(Square $s): string => $s->toAlgebraic(), $squares);
    }

    private static function sq(string $name): Square
    {
        return Square::fromAlgebraic($name);
    }

    public function testKnightTargetsFollowJumpOrder(): void
    {
        self::assertSame(['b3', 'c2'], self::names(BoardGeometry::knightTargets(self::sq('a1'))));
    }

    public function testKingTargetsFollowStepOrder(): void
    {
        self::assertSame(['f1', 'd1', 'e2', 'f2', 'd2'], self::names(BoardGeometry::kingTargets(self::sq('e1'))));
    }

    public function testOrthogonalRaysOneEmptyListPerBlockedDirection(): void
    {
        self::assertSame(
            [
                ['b1', 'c1', 'd1', 'e1', 'f1', 'g1', 'h1'],
                [],
                ['a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8'],
                [],
            ],
            array_map(self::names(...), BoardGeometry::orthogonalRays(self::sq('a1'))),
        );
    }

    public function testDiagonalRayNearestSquareFirst(): void
    {
        self::assertSame(['e5', 'f6', 'g7', 'h8'], self::names(BoardGeometry::diagonalRays(self::sq('d4'))[0]));
    }

    public function testQueenRaysCoverEveryDirection(): void
    {
        $rays = BoardGeometry::queenRays(self::sq('e4'));

        self::assertCount(8, $rays);
        self::assertSame(27, array_sum(array_map(count(...), $rays)));
    }
}
