<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\ValidationException;
use LeCercle\User\UserFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserFieldsTest extends TestCase
{
    public function testPseudoIsTrimmed(): void
    {
        self::assertSame('alice_1', UserFields::pseudo(' alice_1 '));
    }

    /** @return iterable<string, array{string}> */
    public static function badPseudos(): iterable
    {
        yield 'trop court' => ['a'];
        yield 'trop long' => ['trop-long-pseudo-17'];
        yield 'accent' => ['é-accent'];
        yield 'espace' => ['a b'];
        yield 'vide' => [''];
        yield 'saut de ligne final' => ["ab\n" . 'c' . "\n"];
    }

    #[DataProvider('badPseudos')]
    public function testPseudoRejected(string $pseudo): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Pseudo : 2 à 16 caractères (lettres, chiffres, - _).');
        UserFields::pseudo($pseudo);
    }

    public function testEmailIsTrimmedAndLowercased(): void
    {
        self::assertSame('alice@exemple.fr', UserFields::email(' Alice@Exemple.FR '));
    }

    public function testEmailAtMaxLengthIsValid(): void
    {
        $email = str_repeat('a', 115) . '@b.fr';
        self::assertSame(120, strlen($email));
        self::assertSame($email, UserFields::email($email));
    }

    /** @return iterable<string, array{string}> */
    public static function badEmails(): iterable
    {
        yield 'sans arobase' => ['alice'];
        yield 'sans point' => ['a@b'];
        yield 'extension courte' => ['a@b.c'];
        yield 'trop long' => [str_repeat('a', 116) . '@b.fr'];
        yield 'espace interne' => ['a b@c.fr'];
    }

    #[DataProvider('badEmails')]
    public function testEmailRejected(string $email): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Adresse e-mail invalide.');
        UserFields::email($email);
    }

    public function testPasswordAccepted(): void
    {
        self::assertSame('secret', UserFields::password('secret'));
    }

    public function testPasswordTooShort(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Mot de passe : 6 caractères minimum.');
        UserFields::password('court');
    }

    public function testPasswordTooLong(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Mot de passe : 72 caractères maximum.');
        UserFields::password(str_repeat('x', 73));
    }

    public function testPasswordAtMaxLengthIsValid(): void
    {
        $p = str_repeat('x', 72);
        self::assertSame($p, UserFields::password($p));
    }
}
