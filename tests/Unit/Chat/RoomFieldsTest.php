<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\RoomFields;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\TestCase;

final class RoomFieldsTest extends TestCase
{
    public function testNameIsTrimmed(): void
    {
        self::assertSame('Analyses', RoomFields::name(' Analyses '));
    }

    public function testNameTooShort(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nom trop court (2 caractères minimum).');
        RoomFields::name('A');
    }

    public function testNameTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Nom trop long (40 caractères maximum).');
        RoomFields::name(str_repeat('x', 41));
    }

    public function testNameBoundsAreAccepted(): void
    {
        self::assertSame('Ab', RoomFields::name('Ab'));
        self::assertSame(40, mb_strlen(RoomFields::name(str_repeat('é', 40))));
    }

    public function testDescriptionTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Description trop longue (200 caractères maximum).');
        RoomFields::description(str_repeat('x', 201));
    }

    public function testBlankDescriptionBecomesEmpty(): void
    {
        self::assertSame('', RoomFields::description('  '));
        self::assertSame(200, mb_strlen(RoomFields::description(str_repeat('é', 200))));
    }
}
