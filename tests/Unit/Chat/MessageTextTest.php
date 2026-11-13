<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chat;

use LeCercle\Chat\MessageText;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTextTest extends TestCase
{
    public function testTrimsText(): void
    {
        self::assertSame('Bonjour', MessageText::clean('  Bonjour  '));
    }

    public function testAcceptsExactlyMaxCharacters(): void
    {
        self::assertSame(500, mb_strlen(MessageText::clean(str_repeat('é', 500))));
    }

    /** @return array<string, array{string}> */
    public static function emptyTexts(): array
    {
        return ['vide' => [''], 'espaces' => ['   '], 'blancs' => ["\n\t"]];
    }

    #[DataProvider('emptyTexts')]
    public function testRejectsEmptyText(string $text): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Message vide.');
        MessageText::clean($text);
    }

    public function testRejectsTooLongText(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Message trop long (500 caractères maximum).');
        MessageText::clean(str_repeat('é', 501));
    }
}
