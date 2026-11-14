<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

/**
 * Un fichier de migration : son nom (sans « .sql ») et ses instructions SQL.
 */
final readonly class Migration
{
    /**
     * @param list<string> $statements
     */
    public function __construct(
        public string $name,
        public array $statements,
    ) {}
}
