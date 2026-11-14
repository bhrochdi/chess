<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\PlainPasswordHasher;
use PHPUnit\Framework\TestCase;

final class PlainPasswordHasherTest extends TestCase
{
    public function testHashAndVerify(): void
    {
        $hasher = new PlainPasswordHasher();
        self::assertSame('plain:a', $hasher->hash('a'));
        self::assertTrue($hasher->verify('a', $hasher->hash('a')));
        self::assertFalse($hasher->verify('b', $hasher->hash('a')));
    }

    public function testOldPrefixIsVerifiableAndNeedsRehash(): void
    {
        $hasher = new PlainPasswordHasher();
        self::assertTrue($hasher->verify('secret1', 'old:secret1'));
        self::assertFalse($hasher->verify('autre', 'old:secret1'));
        self::assertTrue($hasher->needsRehash('old:secret1'));
        self::assertFalse($hasher->needsRehash('plain:secret1'));
    }
}
