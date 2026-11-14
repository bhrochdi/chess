<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\TimeControl;
use LeCercle\Tournament\PairingGames;

/** Double de test : crée des identifiants de partie g000000000001, g000000000002… et garde les appels. */
final class RecordingPairingGames implements PairingGames
{
    /** @var list<array{white: string, black: string, tc: ?TimeControl}> */
    public array $created = [];

    public function create(string $whiteId, string $blackId, ?TimeControl $tc): string
    {
        $this->created[] = ['white' => $whiteId, 'black' => $blackId, 'tc' => $tc];

        return \sprintf('g%012d', \count($this->created));
    }
}
