<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\PgnWriter;
use PHPUnit\Framework\TestCase;

final class PgnWriterTest extends TestCase
{
    private const HEAD = "[Event \"Partie en ligne — Le Cercle\"]\n[Site \"Le Cercle\"]\n[Date \"2026.11.12\"]\n";

    /** @param list<string> $moves */
    private function write(string $white, string $black, string $result, array $moves): string
    {
        return (new PgnWriter())->write(
            'Partie en ligne — Le Cercle',
            new \DateTimeImmutable('2026-11-12 10:00:00'),
            $white,
            $black,
            $result,
            $moves,
        );
    }

    public function testScholarsMate(): void
    {
        $pgn = $this->write('alice', 'bob', '1-0', ['e4', 'e5', 'Bc4', 'Nc6', 'Qh5', 'Nf6', 'Qxf7#']);

        self::assertSame(
            self::HEAD . "[White \"alice\"]\n[Black \"bob\"]\n[Result \"1-0\"]\n\n"
            . "1. e4 e5 2. Bc4 Nc6 3. Qh5 Nf6 4. Qxf7# 1-0\n",
            $pgn,
        );
    }

    public function testLongGamesWrapAfterSeventyCharacters(): void
    {
        $moves = [];
        for ($i = 0; $i < 16; ++$i) {
            $moves[] = ['Nf3', 'Nf6', 'Ng1', 'Ng8'][$i % 4];
        }
        $pgn = $this->write('a', 'b', '*', $moves);

        self::assertStringEndsWith(
            "\n\n1. Nf3 Nf6 2. Ng1 Ng8 3. Nf3 Nf6 4. Ng1 Ng8 5. Nf3 Nf6 6. Ng1 Ng8 7. Nf3 Nf6\n8. Ng1 Ng8 *\n",
            $pgn,
        );
    }

    public function testOddNumberOfPlies(): void
    {
        self::assertStringEndsWith("\n\n1. e4 *\n", $this->write('a', 'b', '*', ['e4']));
    }

    public function testHeaderValuesAreEscaped(): void
    {
        $pgn = $this->write('a"b\\c', 'x', '*', []);

        self::assertStringContainsString("[White \"a\\\"b\\\\c\"]\n", $pgn);
    }
}
