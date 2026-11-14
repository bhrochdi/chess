<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\MinimaxChooser;
use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\MoveGenerator;
use PHPUnit\Framework\TestCase;

final class AiLevelTest extends TestCase
{
    public function testDepthAndFuzzPerLevel(): void
    {
        self::assertSame([2, 60], [AiLevel::from('ai-2')->depth(), AiLevel::from('ai-2')->fuzz()]);
        self::assertSame([3, 12], [AiLevel::from('ai-3')->depth(), AiLevel::from('ai-3')->fuzz()]);
        self::assertSame([4, 0], [AiLevel::from('ai-4')->depth(), AiLevel::from('ai-4')->fuzz()]);
    }

    public function testUnknownLevelIsRejected(): void
    {
        $this->expectException(\ValueError::class);

        AiLevel::from('ai-9');
    }

    public function testChooserIsMinimax(): void
    {
        $chooser = AiLevel::Club->chooser(new MoveGenerator(new AttackDetector()), new FixedRandom(0.5));

        self::assertInstanceOf(MinimaxChooser::class, $chooser);
    }
}
