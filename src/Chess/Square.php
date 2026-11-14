<?php

declare(strict_types=1);

namespace LeCercle\Chess;

use InvalidArgumentException;

/**
 * Case de l'échiquier : colonne 0-7 (a-h), rangée 0-7 (1-8), index = colonne + 8 * rangée.
 * Une seule instance par case, partagée.
 */
final readonly class Square
{
    private function __construct(private int $index) {}

    public static function at(int $file, int $rank): self
    {
        if ($file < 0 || $file > 7 || $rank < 0 || $rank > 7) {
            throw new InvalidArgumentException("Coordonnées hors échiquier : {$file}, {$rank}");
        }

        return self::all()[$file + 8 * $rank];
    }

    public static function fromIndex(int $i): self
    {
        if ($i < 0 || $i > 63) {
            throw new InvalidArgumentException("Index de case invalide : {$i}");
        }

        return self::all()[$i];
    }

    public static function fromAlgebraic(string $s): self
    {
        if (preg_match('/^[a-h][1-8]\z/', $s) !== 1) {
            throw new InvalidArgumentException("Notation de case invalide : '{$s}'");
        }

        return self::at(ord($s[0]) - ord('a'), (int) $s[1] - 1);
    }

    public function file(): int
    {
        return $this->index & 7;
    }

    public function rank(): int
    {
        return $this->index >> 3;
    }

    public function index(): int
    {
        return $this->index;
    }

    public function toAlgebraic(): string
    {
        return chr(ord('a') + $this->file()) . ($this->rank() + 1);
    }

    /** Case décalée de ($df, $dr), ou null si elle sort de l'échiquier. */
    public function offset(int $df, int $dr): ?self
    {
        $file = ($this->index & 7) + $df;
        $rank = ($this->index >> 3) + $dr;
        if ($file < 0 || $file > 7 || $rank < 0 || $rank > 7) {
            return null;
        }

        return self::all()[$file + 8 * $rank];
    }

    /**
     * Les 64 cases, construites au premier appel (une classe readonly n'a pas de propriété statique).
     *
     * @return list<self>
     */
    private static function all(): array
    {
        static $squares = null;

        return $squares ??= array_map(static fn(int $i): self => new self($i), range(0, 63));
    }
}
