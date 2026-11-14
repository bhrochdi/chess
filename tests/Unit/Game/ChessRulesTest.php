<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\EndReason;
use LeCercle\Chess\Outcome;
use LeCercle\Chess\Position;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChessRulesTest extends TestCase
{
    private ChessRules $rules;

    protected function setUp(): void
    {
        $this->rules = ChessRules::standard();
    }

    public function testMoveFromUciFindsALegalMove(): void
    {
        self::assertSame('e2e4', $this->rules->moveFromUci(Position::initial(), 'e2e4')->toUci());
    }

    /** @return iterable<string, array{string}> */
    public static function illegalUcis(): iterable
    {
        yield 'case inaccessible' => ['e2e5'];
        yield 'mauvais camp' => ['e7e5'];
        yield 'promotion sans objet' => ['e2e4q'];
        yield 'majuscules' => ['E2E4'];
        yield 'tronqué' => ['e2'];
        yield 'vide' => [''];
        yield 'roque pas encore permis' => ['e1g1'];
    }

    #[DataProvider('illegalUcis')]
    public function testMoveFromUciRejectsAnIllegalMove(string $uci): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Coup illégal.');
        $this->rules->moveFromUci(Position::initial(), $uci);
    }

    public function testPromotionNeedsItsPiece(): void
    {
        $p = Position::fromFen('8/P7/8/8/8/8/8/k6K w - - 0 1');
        self::assertSame('a7a8q', $this->rules->moveFromUci($p, 'a7a8q')->toUci());

        $this->expectException(ValidationException::class);
        $this->rules->moveFromUci($p, 'a7a8');
    }

    public function testSanLegalUcisAndCheck(): void
    {
        $initial = Position::initial();
        self::assertSame('e4', $this->rules->san($initial, $this->rules->moveFromUci($initial, 'e2e4')));
        self::assertCount(20, $this->rules->legalUcis($initial));
        self::assertContains('g1f3', $this->rules->legalUcis($initial));
        self::assertFalse($this->rules->inCheck($initial));
    }

    public function testOutcomeAfterScholarsMate(): void
    {
        $p = Position::initial();
        foreach (['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7'] as $uci) {
            $p = $p->apply($this->rules->moveFromUci($p, $uci));
        }

        self::assertTrue($this->rules->inCheck($p));
        self::assertEquals(new Outcome(Result::WhiteWins, EndReason::Checkmate), $this->rules->outcome($p, [$p->repetitionKey()]));
    }
}
