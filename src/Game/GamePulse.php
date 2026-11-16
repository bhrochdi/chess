<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Color;
use LeCercle\Shared\Millis;

/**
 * État léger d'une partie, lu sans ses coups : de quoi répondre à une relecture périodique
 * « rien n'a changé » sans recharger ni rejouer la partie.
 */
final readonly class GamePulse
{
    public function __construct(
        public GameMode $mode,
        public string $ownerId,
        public GameStatus $status,
        public int $plyCount,
        public ?Color $drawOffer,
        public ?GameClock $clock,
        public \DateTimeImmutable $updatedAt,
    ) {}

    public static function of(Game $game): self
    {
        return new self(
            $game->mode(),
            $game->ownerId(),
            $game->status(),
            $game->plyCount(),
            $game->drawOffer(),
            $game->clock(),
            $game->updatedAt(),
        );
    }

    /**
     * Jeton d'état : change à chaque écriture de la partie (coup, siège pris, proposition de nulle, fin,
     * annulation). Le navigateur le renvoie pour savoir si son affichage est à jour.
     */
    public function version(): string
    {
        return implode('.', [
            Millis::of($this->updatedAt),
            $this->plyCount,
            $this->status->value,
            $this->drawOffer->value ?? '-',
        ]);
    }

    /** Le camp au trait n'a plus de temps : la partie doit être relue en entier pour que la chute soit enregistrée. */
    public function flagFallen(\DateTimeImmutable $now): bool
    {
        return $this->status === GameStatus::Playing && $this->clock?->flagged($now) !== null;
    }
}
