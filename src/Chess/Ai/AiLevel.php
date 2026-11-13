<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

use LeCercle\Chess\MoveGenerator;

/** Niveaux de l'IA ; les valeurs sont celles du sélecteur `localMode` du front. */
enum AiLevel: string
{
    case Beginner = 'ai-2';
    case Club = 'ai-3';
    case Master = 'ai-4';

    /** Profondeur de recherche en demi-coups. */
    public function depth(): int
    {
        return match ($this) {
            self::Beginner => 2,
            self::Club => 3,
            self::Master => 4,
        };
    }

    /** Amplitude du bruit ajouté à la note de chaque coup racine, en centipions. */
    public function fuzz(): int
    {
        return match ($this) {
            self::Beginner => 60,
            self::Club => 12,
            self::Master => 0,
        };
    }

    public function chooser(MoveGenerator $moves, RandomSource $random): MoveChooser
    {
        return new MinimaxChooser($moves, new PieceSquareEvaluator(), $random, $this->depth(), $this->fuzz());
    }
}
