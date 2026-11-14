<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;

/**
 * Agrégat tournoi : inscriptions, lancement, résultats, rondes et clôture.
 * Les règles d'accès (admin, comptes invités) et « un seul tournoi en cours »
 * relèvent du service.
 */
final class Tournament
{
    private const NOT_RUNNING = 'Le tournoi n’est pas en cours.';
    private const STARTED = 'Les inscriptions sont closes : le tournoi a commencé.';

    /**
     * @param list<string> $players
     * @param list<Round>  $rounds
     */
    private function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $description,
        private readonly TournamentFormat $format,
        private readonly ?TimeControl $timeControl,
        private TournamentStatus $status,
        private bool $registrationOpen,
        private readonly ?int $plannedRounds,
        private int $currentRound,
        private array $players,
        private array $rounds,
        private readonly string $createdBy,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $startedAt,
        private ?\DateTimeImmutable $finishedAt,
    ) {}

    /** Le nombre de rondes ne sert qu'au suisse (5 par défaut) ; ignoré pour les autres formats. */
    public static function create(string $id, string $name, string $description, TournamentFormat $format, ?TimeControl $timeControl, ?int $plannedRounds, string $createdBy, \DateTimeImmutable $now): self
    {
        return new self(
            $id,
            TournamentFields::name($name),
            TournamentFields::description($description),
            $format,
            $timeControl,
            TournamentStatus::Registration,
            true,
            $format === TournamentFormat::Swiss ? TournamentFields::plannedRounds($plannedRounds) : null,
            0,
            [],
            [],
            $createdBy,
            $now,
            null,
            null,
        );
    }

    public static function restore(TournamentSnapshot $s): self
    {
        return new self(
            $s->id,
            $s->name,
            $s->description,
            $s->format,
            $s->timeControl,
            $s->status,
            $s->registrationOpen,
            $s->plannedRounds,
            $s->currentRound,
            $s->players,
            $s->rounds,
            $s->createdBy,
            $s->createdAt,
            $s->startedAt,
            $s->finishedAt,
        );
    }

    public function setRegistrationOpen(bool $open): void
    {
        if ($this->status !== TournamentStatus::Registration) {
            throw new ConflictException(self::STARTED);
        }
        $this->registrationOpen = $open;
    }

    public function register(string $userId): void
    {
        if ($this->status !== TournamentStatus::Registration || !$this->registrationOpen) {
            throw new ConflictException('Les inscriptions sont fermées.');
        }
        if (!in_array($userId, $this->players, true)) {
            $this->players[] = $userId;
        }
    }

    public function unregister(string $userId): void
    {
        if ($this->status !== TournamentStatus::Registration) {
            throw new ConflictException(self::STARTED);
        }
        $this->players = array_values(array_filter($this->players, static fn(string $id): bool => $id !== $userId));
    }

    /** @param array<string, string> $pseudos id => pseudo (ordre initial du suisse) */
    public function start(array $pseudos, \DateTimeImmutable $now): void
    {
        if ($this->status !== TournamentStatus::Registration) {
            throw new ConflictException('Le tournoi a déjà commencé.');
        }
        if (count($this->players) < 2) {
            throw new ConflictException('Au moins 2 joueurs inscrits sont nécessaires.');
        }
        $this->rounds = match ($this->format) {
            TournamentFormat::RoundRobin => (new RoundRobin())->rounds($this->players),
            TournamentFormat::Elimination => [(new Elimination())->firstRound($this->players)],
            TournamentFormat::Swiss => [(new Swiss())->nextRound([], $this->standings($pseudos))],
        };
        $this->status = TournamentStatus::Running;
        $this->registrationOpen = false;
        $this->currentRound = 1;
        $this->startedAt = $now;
    }

    /** Seule la ronde en cours d'un tournoi en cours se modifie ; les rondes passées sont figées. */
    public function setResult(int $round, int $board, ?Result $result): Pairing
    {
        if ($this->status === TournamentStatus::Finished) {
            throw new ConflictException('Tournoi terminé.');
        }
        if ($this->status !== TournamentStatus::Running) {
            throw new ConflictException(self::NOT_RUNNING);
        }
        $index = $this->roundIndex($round);
        if ($index === null || $round > $this->currentRound) {
            throw new ConflictException('Cette ronde n’a pas commencé.');
        }
        if ($round < $this->currentRound) {
            throw new ConflictException('Seuls les résultats de la ronde en cours peuvent être modifiés.');
        }
        $pairing = $this->rounds[$index]->pairing($board);
        if ($pairing === null) {
            throw new NotFoundException('Match introuvable.');
        }
        if ($pairing->isBye()) {
            throw new ConflictException('Un exempt n’a pas de résultat à saisir.');
        }
        $updated = $pairing->withResult($result);
        $this->rounds = array_map(
            static fn(Round $r): Round => $r->number === $round ? $r->withPairing($updated) : $r,
            $this->rounds,
        );

        return $updated;
    }

    /**
     * Rencontres de la ronde en cours qui attendent leur partie (deux joueurs, pas encore de partie).
     *
     * @return list<Pairing>
     */
    public function pairingsWithoutGame(): array
    {
        $current = $this->currentRoundOrNull();

        return $current === null ? [] : array_values(array_filter(
            $current->pairings,
            static fn(Pairing $p): bool => $p->whiteId !== null && $p->blackId !== null && $p->gameId === null,
        ));
    }

    public function linkGame(int $board, string $gameId): void
    {
        $this->replacePairing($this->currentRound, $board, static fn(Pairing $p): Pairing => $p->withGame($gameId));
    }

    /** Résultat d'une partie terminée, reporté sur son appariement quelle que soit sa ronde ; faux hors tournoi en cours. */
    public function recordGameResult(string $gameId, Result $result): bool
    {
        if ($this->status !== TournamentStatus::Running) {
            return false;
        }
        foreach ($this->rounds as $round) {
            foreach ($round->pairings as $p) {
                if ($p->gameId === $gameId) {
                    $this->replacePairing($round->number, $p->board, static fn(Pairing $q): Pairing => $q->withResult($result));

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Port de rondeSuivante. $force ne sert qu'au toutes rondes : il passe une ronde incomplète.
     *
     * @param array<string, string> $pseudos id => pseudo (classement du suisse)
     */
    public function nextRound(bool $force, array $pseudos): void
    {
        if ($this->status !== TournamentStatus::Running) {
            throw new ConflictException(self::NOT_RUNNING);
        }
        $current = $this->currentRoundOrNull();
        $incomplete = $current !== null && !$current->isComplete();

        if ($this->format === TournamentFormat::RoundRobin) {
            if ($this->currentRound >= count($this->rounds)) {
                throw new ConflictException('Dernière ronde atteinte : clôture le tournoi.');
            }
            if ($incomplete && !$force) {
                throw new ConflictException('Des résultats manquent à la ronde ' . $this->currentRound . '.');
            }
            ++$this->currentRound;

            return;
        }

        if ($incomplete) {
            throw new ConflictException('Complète tous les résultats de la ronde avant de générer la suivante.');
        }
        if ($this->format === TournamentFormat::Elimination) {
            $next = $current === null ? null : (new Elimination())->nextRound($current);
            if ($next === null) {
                throw new ConflictException('Il ne reste qu’un joueur : clôture le tournoi.');
            }
        } else {
            if ($this->currentRound >= ($this->plannedRounds ?? 0)) {
                throw new ConflictException('Nombre de rondes du suisse atteint : clôture le tournoi.');
            }
            $next = (new Swiss())->nextRound($this->rounds, $this->standings($pseudos));
        }
        $this->rounds[] = $next;
        $this->currentRound = $next->number;
    }

    public function close(\DateTimeImmutable $now): void
    {
        if ($this->status !== TournamentStatus::Running) {
            throw new ConflictException(self::NOT_RUNNING);
        }
        $this->status = TournamentStatus::Finished;
        $this->finishedAt = $now;
    }

    /** Port de peutRondeSuivante, faux hors d'un tournoi en cours. */
    public function canAdvance(): bool
    {
        if ($this->status !== TournamentStatus::Running) {
            return false;
        }

        return match ($this->format) {
            TournamentFormat::RoundRobin => $this->currentRound < count($this->rounds),
            TournamentFormat::Swiss => $this->currentRound < ($this->plannedRounds ?? 0),
            // élimination : il reste un tour tant que la ronde courante compte plus d'un match
            TournamentFormat::Elimination => count($this->currentRoundOrNull()->pairings ?? []) > 1,
        };
    }

    /**
     * @param array<string, string> $pseudos id => pseudo
     * @return list<StandingRow>
     */
    public function standings(array $pseudos): array
    {
        return (new Standings())->compute($this->players, $this->rounds, $pseudos);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function format(): TournamentFormat
    {
        return $this->format;
    }

    public function timeControl(): ?TimeControl
    {
        return $this->timeControl;
    }

    public function status(): TournamentStatus
    {
        return $this->status;
    }

    public function registrationOpen(): bool
    {
        return $this->registrationOpen;
    }

    /** Suisse uniquement. */
    public function plannedRounds(): ?int
    {
        return $this->plannedRounds;
    }

    public function currentRound(): int
    {
        return $this->currentRound;
    }

    /** @return list<string> ordre d'inscription */
    public function players(): array
    {
        return $this->players;
    }

    /** @return list<Round> */
    public function rounds(): array
    {
        return $this->rounds;
    }

    public function createdBy(): string
    {
        return $this->createdBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    /** @param \Closure(Pairing): Pairing $change */
    private function replacePairing(int $round, int $board, \Closure $change): void
    {
        $this->rounds = array_map(
            static function (Round $r) use ($round, $board, $change): Round {
                $p = $r->number === $round ? $r->pairing($board) : null;

                return $p === null ? $r : $r->withPairing($change($p));
            },
            $this->rounds,
        );
    }

    private function roundIndex(int $number): ?int
    {
        foreach ($this->rounds as $i => $r) {
            if ($r->number === $number) {
                return $i;
            }
        }

        return null;
    }

    private function currentRoundOrNull(): ?Round
    {
        $i = $this->roundIndex($this->currentRound);

        return $i === null ? null : $this->rounds[$i];
    }
}
