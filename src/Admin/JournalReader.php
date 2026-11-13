<?php

declare(strict_types=1);

namespace LeCercle\Admin;

/** Lecture du journal d'administration. */
interface JournalReader
{
    /** @return list<array{actorId: string, action: string, at: \DateTimeImmutable}> les plus récentes d'abord */
    public function latest(int $limit): array;
}
