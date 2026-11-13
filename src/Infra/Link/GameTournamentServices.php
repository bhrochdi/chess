<?php

declare(strict_types=1);

namespace LeCercle\Infra\Link;

use LeCercle\Chess\Ai\SystemRandom;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Game\GameEndListener;
use LeCercle\Game\GameService;
use LeCercle\Game\GameViews;
use LeCercle\Game\MinimaxComputerPlayers;
use LeCercle\Infra\Game\PdoGameRepository;
use LeCercle\Infra\Infrastructure;
use LeCercle\Infra\Tournament\PdoTournamentRepository;
use LeCercle\Shared\TimeControl;
use LeCercle\Tournament\PairingGames;
use LeCercle\Tournament\TournamentService;
use LeCercle\Tournament\TournamentViews;

/**
 * Relie parties et tournois sans que l'un connaisse l'autre : un appariement crée sa partie,
 * une partie terminée reporte son résultat dans la même transaction.
 */
final class GameTournamentServices implements GameEndListener, PairingGames
{
    private ?GameService $games = null;
    private ?TournamentService $tournaments = null;

    public function __construct(private readonly Infrastructure $infra) {}

    public function games(): GameService
    {
        if ($this->games === null) {
            $rules = ChessRules::standard();
            $random = new SystemRandom();
            $this->games = new GameService(
                new PdoGameRepository($this->infra->pdo(), $rules),
                $this->infra->access(),
                $this->infra->adminJournal(),
                $this->infra->clock(),
                $this->infra->transactions(),
                $rules,
                new MinimaxComputerPlayers($rules->generator(), $random),
                $random,
                new GameViews($this->infra->members(), $rules),
                $this,
            );
        }

        return $this->games;
    }

    public function tournaments(): TournamentService
    {
        return $this->tournaments ??= new TournamentService(
            new PdoTournamentRepository($this->infra->pdo()),
            $this->infra->access(),
            $this->infra->members(),
            $this->infra->adminJournal(),
            $this->infra->clock(),
            $this->infra->transactions(),
            new TournamentViews(),
            $this,
        );
    }

    public function gameFinished(string $gameId, Result $result): void
    {
        $this->tournaments()->recordGameResult($gameId, $result);
    }

    public function create(string $whiteId, string $blackId, ?TimeControl $tc): string
    {
        return $this->games()->createForPairing($whiteId, $blackId, $tc);
    }
}
