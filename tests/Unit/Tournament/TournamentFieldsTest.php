<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Shared\ValidationException;
use LeCercle\Tournament\TournamentFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TournamentFieldsTest extends TestCase
{
    public function testNameIsTrimmed(): void
    {
        self::assertSame('Open', TournamentFields::name(' Open '));
    }

    public function testNameTooShort(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nom trop court (2 caractères minimum).');
        TournamentFields::name('O');
    }

    public function testNameTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nom trop long (60 caractères maximum).');
        TournamentFields::name(str_repeat('a', 61));
    }

    public function testNameLimitCountsCharactersNotBytes(): void
    {
        self::assertSame(str_repeat('é', 60), TournamentFields::name(str_repeat('é', 60)));
    }

    public function testDescription(): void
    {
        self::assertSame('x', TournamentFields::description(' x '));
        self::assertSame(str_repeat('é', 500), TournamentFields::description(str_repeat('é', 500)));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Description trop longue (500 caractères maximum).');
        TournamentFields::description(str_repeat('a', 501));
    }

    public function testPlannedRoundsDefaultsAndBounds(): void
    {
        self::assertSame(5, TournamentFields::plannedRounds(null));
        self::assertSame(2, TournamentFields::plannedRounds(2));
        self::assertSame(15, TournamentFields::plannedRounds(15));
    }

    #[DataProvider('badRounds')]
    public function testPlannedRoundsOutOfRange(int $rounds): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nombre de rondes : entre 2 et 15.');
        TournamentFields::plannedRounds($rounds);
    }

    /** @return iterable<string, array{int}> */
    public static function badRounds(): iterable
    {
        yield 'one' => [1];
        yield 'sixteen' => [16];
        yield 'zero' => [0];
        yield 'negative' => [-3];
    }
}
