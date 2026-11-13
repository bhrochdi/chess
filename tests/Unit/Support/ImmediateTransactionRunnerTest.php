<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\ImmediateTransactionRunner;
use PHPUnit\Framework\TestCase;

final class ImmediateTransactionRunnerTest extends TestCase
{
    public function testRunsTheWorkAndReturnsItsResult(): void
    {
        self::assertSame(42, (new ImmediateTransactionRunner())->run(static fn(): int => 42));
    }
}
