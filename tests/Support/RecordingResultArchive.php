<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chess\Result;
use LeCercle\Game\ResultArchive;

/** Double de test : garde chaque résultat archivé. */
final class RecordingResultArchive implements ResultArchive
{
    /** @var list<array{string, string, Result}> blancs, noirs, résultat */
    public array $kept = [];

    public function keep(string $whiteId, string $blackId, Result $result): void
    {
        $this->kept[] = [$whiteId, $blackId, $result];
    }
}
