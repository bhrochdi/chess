<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\RandomSource;
use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Member;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Shared\ValidationException;

/**
 * Cas d'usage des parties : chaque écriture passe par Access::member() puis une transaction qui verrouille la partie ;
 * la réponse est construite après les écritures, à un instant relu sur l'horloge.
 */
final class GameService
{
    public const LOBBY_FINISHED_WINDOW = 'PT2H';
    public const LOBBY_LIMIT = 30;
    public const MINE_LIMIT = 10;
    public const ADMIN_LIST_LIMIT = 200;

    private readonly PgnWriter $pgn;

    public function __construct(
        private readonly GameRepository $games,
        private readonly Access $access,
        private readonly AdminJournal $journal,
        private readonly Clock $clock,
        private readonly TransactionRunner $transactions,
        private readonly ChessRules $rules,
        private readonly ComputerPlayers $computer,
        private readonly RandomSource $random,
        private readonly GameViews $views,
        private readonly GameEndListener $ended,
    ) {
        $this->pgn = new PgnWriter();
    }

    /**
     * @param array<mixed>|null $timeControl
     *
     * @return array<string, mixed>
     */
    public function create(string $mode, ?string $level, ?array $timeControl, ?string $color): array
    {
        $actor = $this->access->member();
        $gameMode = GameMode::tryFrom($mode) ?? throw new ValidationException('Mode de partie inconnu.');
        $aiLevel = $gameMode === GameMode::Computer
            ? AiLevel::tryFrom($level ?? '') ?? throw new ValidationException('Niveau inconnu.')
            : null;
        $creatorColor = $gameMode === GameMode::Online ? $this->creatorColor($color) : null;
        $tc = TimeControl::fromArray($timeControl);

        $game = $this->transactions->run(function () use ($actor, $gameMode, $aiLevel, $creatorColor, $tc): Game {
            $id = $this->games->nextId();
            $now = $this->clock->now();
            $game = match ($gameMode) {
                GameMode::Local => Game::local($id, $actor->id, $tc, $now),
                GameMode::Computer => Game::againstComputer($id, $actor->id, $aiLevel ?? throw new \LogicException('Niveau absent.'), $tc, $now),
                GameMode::Online => Game::online($id, $actor->id, $creatorColor ?? throw new \LogicException('Couleur absente.'), $tc, $now),
            };
            $this->games->add($game);

            return $game;
        });

        return $this->view($game, $actor);
    }

    /** @return array<string, mixed> */
    public function join(string $id): array
    {
        return $this->write($id, static function (Game $game, Member $actor, \DateTimeImmutable $now): void {
            // Une partie locale ou contre l'ordinateur commence à sa création : il n'y a pas de siège à prendre.
            if ($game->mode() !== GameMode::Online) {
                throw new ConflictException('Cette partie a déjà commencé.');
            }
            $game->join($actor->id, $now);
        });
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        $viewer = $this->access->viewer();

        return $this->view($this->readable($id, $viewer), $viewer);
    }

    /** @return array<string, mixed> */
    public function move(string $id, string $uci, int $ply): array
    {
        return $this->playerWrite($id, function (Game $game, Member $actor, \DateTimeImmutable $now) use ($uci, $ply): void {
            if ($ply !== $game->plyCount()) {
                throw new ConflictException('La partie a avancé : recharge-la.');
            }
            $game->play($actor->id, $uci, $this->rules, $now);
            if ($game->computerToMove()) {
                $this->computerReplies($game);
            }
        });
    }

    /** @return array<string, mixed> */
    public function resign(string $id): array
    {
        return $this->playerWrite($id, static function (Game $game, Member $actor, \DateTimeImmutable $now): void {
            $game->resign($actor->id, $now);
        });
    }

    /** @return array<string, mixed> */
    public function offerDraw(string $id): array
    {
        return $this->playerWrite($id, static function (Game $game, Member $actor, \DateTimeImmutable $now): void {
            $game->offerDraw($actor->id, $now);
        });
    }

    /** @return array<string, mixed> */
    public function acceptDraw(string $id): array
    {
        return $this->playerWrite($id, static function (Game $game, Member $actor, \DateTimeImmutable $now): void {
            $game->acceptDraw($actor->id, $now);
        });
    }

    /** @return array<string, mixed> */
    public function undo(string $id): array
    {
        return $this->write($id, function (Game $game, Member $actor, \DateTimeImmutable $now): void {
            $game->undo($actor->id, $this->rules, $now);
        });
    }

    /** Partie d'un appariement de tournoi ; appelée par le lien tournoi-parties, sans contrôle d'accès. */
    public function createForPairing(string $whiteId, string $blackId, ?TimeControl $tc): string
    {
        return $this->transactions->run(function () use ($whiteId, $blackId, $tc): string {
            $game = Game::forTournament($this->games->nextId(), $whiteId, $blackId, $tc, $this->clock->now());
            $this->games->add($game);

            return $game->id();
        });
    }

    /** @return list<array<string, mixed>> */
    public function adminList(): array
    {
        $this->access->admin();

        return $this->views->summaries($this->games->all(self::ADMIN_LIST_LIMIT));
    }

    /** @return array<string, mixed> */
    public function adminClose(string $id, ?string $result): array
    {
        $admin = $this->access->admin();
        $parsed = $result === null ? Result::Draw : (Result::tryFrom($result) ?? throw new ValidationException('Résultat inconnu.'));
        $game = $this->transactions->run(function () use ($id, $admin, $parsed): Game {
            $game = $this->games->getForUpdate($id);
            $game->closeByAdmin($parsed, $this->clock->now());
            $this->games->save($game);
            $this->ended->gameFinished($game->id(), $parsed);
            $this->journal->record($admin->id, 'Clôture administrative de la partie ' . $game->id());

            return $game;
        });

        return $this->view($game, $admin);
    }

    public function adminDelete(string $id): void
    {
        $admin = $this->access->admin();
        $this->transactions->run(function () use ($id, $admin): void {
            $game = $this->games->getForUpdate($id);
            $this->games->delete($game->id());
            $this->journal->record($admin->id, 'Suppression de la partie ' . $game->id());
        });
    }

    /** Supprime les parties terminées ; renvoie leur nombre. */
    public function purgeFinished(): int
    {
        $admin = $this->access->admin();

        return $this->transactions->run(function () use ($admin): int {
            $ids = $this->games->finishedIds();
            foreach ($ids as $id) {
                $this->games->delete($id);
            }
            $this->journal->record($admin->id, 'Purge de ' . \count($ids) . ' partie(s) terminée(s)');

            return \count($ids);
        });
    }

    /** @return list<array<string, mixed>> */
    public function lobby(): array
    {
        $since = $this->clock->now()->sub(new \DateInterval(self::LOBBY_FINISHED_WINDOW));

        return $this->views->summaries($this->games->lobby($since, self::LOBBY_LIMIT));
    }

    /** @return list<array<string, mixed>> */
    public function mine(): array
    {
        $actor = $this->access->member();

        return $this->views->summaries($this->games->unfinishedOf($actor->id, self::MINE_LIMIT));
    }

    /** @return array{filename: string, pgn: string} */
    public function pgn(string $id): array
    {
        $game = $this->readable($id, $this->access->viewer());
        [$white, $black] = $this->views->playerNames($game);

        return [
            'filename' => 'le-cercle-' . $game->id() . '.pgn',
            'pgn' => $this->pgn->write(
                $game->mode() === GameMode::Online ? 'Partie en ligne — Le Cercle' : 'Partie locale',
                $game->createdAt()->setTimezone(new \DateTimeZone('UTC')),
                $white,
                $black,
                $game->result()->value ?? '*',
                array_map(static fn(PlayedMove $m): string => $m->san, $game->moves()),
            ),
        ];
    }

    /**
     * Écriture réservée aux joueurs : un étranger est refusé avant tout ; ensuite une chute du drapeau
     * l'emporte sur la demande, qui n'est alors pas appliquée.
     *
     * @param \Closure(Game, Member, \DateTimeImmutable): void $change
     *
     * @return array<string, mixed>
     */
    private function playerWrite(string $id, \Closure $change): array
    {
        return $this->write($id, static function (Game $game, Member $actor, \DateTimeImmutable $now) use ($change): void {
            if ($game->colorsOf($actor->id) === []) {
                throw new ForbiddenException('Tu ne joues pas cette partie.');
            }
            if (!$game->checkFlag($now)) {
                $change($game, $actor, $now);
            }
        });
    }

    /**
     * @param \Closure(Game, Member, \DateTimeImmutable): void $change
     *
     * @return array<string, mixed>
     */
    private function write(string $id, \Closure $change): array
    {
        $actor = $this->access->member();
        $game = $this->transactions->run(function () use ($id, $actor, $change): Game {
            $game = $this->visible($this->games->getForUpdate($id), $actor);
            $wasFinished = $game->status() === GameStatus::Finished;
            $change($game, $actor, $this->clock->now());
            $this->games->save($game);
            if (!$wasFinished) {
                $this->announceEnd($game);
            }

            return $game;
        });

        return $this->view($game, $actor);
    }

    /** Lecture sans verrou ; si le drapeau est tombé depuis, la fin est enregistrée sous verrou. */
    private function readable(string $id, ?Member $viewer): Game
    {
        $game = $this->visible($this->games->get($id), $viewer);
        if (!$game->checkFlag($this->clock->now())) {
            return $game;
        }

        return $this->transactions->run(function () use ($id): Game {
            $game = $this->games->getForUpdate($id);
            if ($game->checkFlag($this->clock->now())) {
                $this->games->save($game);
                $this->announceEnd($game);
            }

            return $game;
        });
    }

    private function announceEnd(Game $game): void
    {
        $result = $game->result();
        if ($game->status() === GameStatus::Finished && $result !== null) {
            $this->ended->gameFinished($game->id(), $result);
        }
    }

    /** Une partie locale ou contre l'ordinateur n'existe que pour son propriétaire. */
    private function visible(Game $game, ?Member $viewer): Game
    {
        if ($game->mode() !== GameMode::Online && $game->ownerId() !== $viewer?->id) {
            throw new NotFoundException('Partie introuvable.');
        }

        return $game;
    }

    /** Le coup de l'ordinateur est daté après son choix : son temps de réflexion est décompté de sa pendule. */
    private function computerReplies(Game $game): void
    {
        $level = $game->level() ?? throw new \LogicException("Partie {$game->id()} contre l'ordinateur sans niveau.");
        $move = $this->computer->chooserFor($level)->choose($game->position())
            ?? throw new \LogicException("Partie {$game->id()} : l'ordinateur n'a aucun coup légal.");
        $game->playComputer($move, $this->rules, $this->clock->now());
    }

    private function creatorColor(?string $color): Color
    {
        return match ($color) {
            'w' => Color::White,
            'b' => Color::Black,
            'random', null => $this->random->float() < 0.5 ? Color::White : Color::Black,
            default => throw new ValidationException('Couleur inconnue.'),
        };
    }

    /**
     * Vue construite après les écritures, à un instant relu : les temps restants ne sont pas surestimés.
     *
     * @return array<string, mixed>
     */
    private function view(Game $game, ?Member $viewer): array
    {
        return $this->views->game($game, $viewer, $this->clock->now());
    }
}
