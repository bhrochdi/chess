<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/**
 * @internal Directions des pièces, rayons et sauts précalculés par case (calculés au premier appel).
 * L'ordre des directions fixe l'ordre de génération des coups : ne pas le changer.
 */
final class BoardGeometry
{
    public const KNIGHT_JUMPS = [[1, 2], [2, 1], [2, -1], [1, -2], [-1, -2], [-2, -1], [-2, 1], [-1, 2]];
    public const KING_STEPS = [[1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [1, -1], [-1, 1], [-1, -1]];
    public const ORTHOGONAL = [[1, 0], [-1, 0], [0, 1], [0, -1]];
    public const DIAGONAL = [[1, 1], [1, -1], [-1, 1], [-1, -1]];
    public const QUEEN = [...self::ORTHOGONAL, ...self::DIAGONAL];

    /** @var array<int, list<list<Square>>> */
    private static array $orthogonalRays = [];

    /** @var array<int, list<list<Square>>> */
    private static array $diagonalRays = [];

    /** @var array<int, list<list<Square>>> */
    private static array $queenRays = [];

    /** @var array<int, list<Square>> */
    private static array $knightTargets = [];

    /** @var array<int, list<Square>> */
    private static array $kingTargets = [];

    /** @return list<list<Square>> Un rayon par direction, case la plus proche en premier. */
    public static function orthogonalRays(Square $s): array
    {
        return self::$orthogonalRays[$s->index()] ??= self::rays($s, self::ORTHOGONAL);
    }

    /** @return list<list<Square>> */
    public static function diagonalRays(Square $s): array
    {
        return self::$diagonalRays[$s->index()] ??= self::rays($s, self::DIAGONAL);
    }

    /** @return list<list<Square>> */
    public static function queenRays(Square $s): array
    {
        return self::$queenRays[$s->index()] ??= self::rays($s, self::QUEEN);
    }

    /** @return list<Square> */
    public static function knightTargets(Square $s): array
    {
        return self::$knightTargets[$s->index()] ??= self::targets($s, self::KNIGHT_JUMPS);
    }

    /** @return list<Square> */
    public static function kingTargets(Square $s): array
    {
        return self::$kingTargets[$s->index()] ??= self::targets($s, self::KING_STEPS);
    }

    /**
     * @param list<array{int, int}> $dirs
     *
     * @return list<list<Square>>
     */
    private static function rays(Square $s, array $dirs): array
    {
        $rays = [];
        foreach ($dirs as [$df, $dr]) {
            $ray = [];
            for ($t = $s->offset($df, $dr); $t !== null; $t = $t->offset($df, $dr)) {
                $ray[] = $t;
            }
            $rays[] = $ray;
        }

        return $rays;
    }

    /**
     * @param list<array{int, int}> $steps
     *
     * @return list<Square>
     */
    private static function targets(Square $s, array $steps): array
    {
        $targets = [];
        foreach ($steps as [$df, $dr]) {
            $t = $s->offset($df, $dr);
            if ($t !== null) {
                $targets[] = $t;
            }
        }

        return $targets;
    }
}
