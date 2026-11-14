<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Shared;

use LeCercle\Shared\TimeControl;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeControlTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $tc = TimeControl::fromArray(['base' => 300, 'inc' => 2]);

        self::assertNotNull($tc);
        self::assertSame(['base' => 300, 'inc' => 2], $tc->toArray());
    }

    public function testNullStaysNull(): void
    {
        self::assertNull(TimeControl::fromArray(null));
    }

    /** @param array<mixed> $data */
    #[DataProvider('invalid')]
    public function testInvalidIsRefused(array $data): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Cadence invalide.');
        TimeControl::fromArray($data);
    }

    /** @return iterable<string, array{array<mixed>}> */
    public static function invalid(): iterable
    {
        yield 'base zero' => [['base' => 0, 'inc' => 0]];
        yield 'base too big' => [['base' => 10801, 'inc' => 0]];
        yield 'inc negative' => [['base' => 300, 'inc' => -1]];
        yield 'inc too big' => [['base' => 300, 'inc' => 181]];
        yield 'base string' => [['base' => '300', 'inc' => 0]];
        yield 'inc missing' => [['base' => 300]];
        yield 'empty' => [[]];
        yield 'base float' => [['base' => 300.5, 'inc' => 0]];
    }
}
