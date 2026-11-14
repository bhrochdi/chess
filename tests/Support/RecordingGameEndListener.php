<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chess\Result;
use LeCercle\Game\GameEndListener;

/** Double de test : garde chaque fin de partie annoncée. */
final class RecordingGameEndListener implements GameEndListener
{
    /** @var list<array{string, Result}> */
    public array $finished = [];

    public function gameFinished(string $gameId, Result $result): void
    {
        $this->finished[] = [$gameId, $result];
    }
}
