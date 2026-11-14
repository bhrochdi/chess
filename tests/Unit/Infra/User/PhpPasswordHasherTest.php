<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\User;

use LeCercle\Infra\User\PhpPasswordHasher;
use PHPUnit\Framework\TestCase;

final class PhpPasswordHasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hasher = new PhpPasswordHasher();
        $hash = $hasher->hash('motdepasse');
        self::assertStringStartsWith('$2y$', $hash);
        self::assertTrue($hasher->verify('motdepasse', $hash));
        self::assertFalse($hasher->verify('autre', $hash));
    }

    public function testTwoHashesOfTheSamePasswordDiffer(): void
    {
        $hasher = new PhpPasswordHasher();
        self::assertNotSame($hasher->hash('motdepasse'), $hasher->hash('motdepasse'));
    }

    public function testNeedsRehash(): void
    {
        $hasher = new PhpPasswordHasher();
        self::assertFalse($hasher->needsRehash($hasher->hash('x')));
        self::assertTrue($hasher->needsRehash(password_hash('x', PASSWORD_BCRYPT, ['cost' => 4])));
    }
}
