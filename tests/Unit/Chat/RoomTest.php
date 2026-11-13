<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\Room;
use LeCercle\Shared\Millis;
use PHPUnit\Framework\TestCase;

final class RoomTest extends TestCase
{
    private function general(): Room
    {
        return new Room('general', 'Général', '', 0, false, null, Millis::toDateTime(1794477600000));
    }

    public function testGeneralIsDefault(): void
    {
        self::assertTrue($this->general()->isDefault());
        self::assertFalse((new Room('r1', 'Autre', '', 1, false, null, Millis::toDateTime(0)))->isDefault());
    }

    private function filled(): Room
    {
        return new Room('r000000000001', 'Analyses', 'Parties commentées', 2, false, 'u000000000009', Millis::toDateTime(1794477600123));
    }

    public function testRenamedKeepsIdentityAndOtherFields(): void
    {
        $at = Millis::toDateTime(1794477600123);

        self::assertEquals(
            new Room('r000000000001', 'X', 'Parties commentées', 2, false, 'u000000000009', $at),
            $this->filled()->renamed('X'),
        );
    }

    public function testWithPositionAndArchived(): void
    {
        $at = Millis::toDateTime(1794477600123);

        self::assertEquals(
            new Room('r000000000001', 'Analyses', 'Parties commentées', 3, true, 'u000000000009', $at),
            $this->filled()->withPosition(3)->withArchived(true),
        );
    }
}
