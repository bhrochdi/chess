<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Exécute un travail dans une transaction : tout est validé, ou rien en cas d'exception. */
interface TransactionRunner
{
    /**
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function run(callable $work): mixed;
}
