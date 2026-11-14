<?php

declare(strict_types=1);

namespace LeCercle\Game;

/** Export PGN d'une partie, au même format que l'export du front (exportPGN). */
final class PgnWriter
{
    private const WRAP_AFTER = 70;

    /** @param list<string> $sanMoves */
    public function write(
        string $event,
        \DateTimeImmutable $date,
        string $white,
        string $black,
        string $result,
        array $sanMoves,
    ): string {
        $pgn = $this->header('Event', $event)
            . $this->header('Site', 'Le Cercle')
            . $this->header('Date', $date->format('Y.m.d'))
            . $this->header('White', $white)
            . $this->header('Black', $black)
            . $this->header('Result', $result)
            . "\n";

        $line = '';
        $count = count($sanMoves);
        for ($i = 0; $i < $count; $i += 2) {
            $line .= ($i / 2 + 1) . '. ' . $sanMoves[$i];
            if (isset($sanMoves[$i + 1])) {
                $line .= ' ' . $sanMoves[$i + 1];
            }
            $line .= ' ';
            if (strlen($line) > self::WRAP_AFTER) {
                $pgn .= trim($line) . "\n";
                $line = '';
            }
        }

        return $pgn . $line . $result . "\n";
    }

    private function header(string $name, string $value): string
    {
        return '[' . $name . ' "' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . "\"]\n";
    }
}
