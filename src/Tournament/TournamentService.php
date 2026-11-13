<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Member;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\TimeControl;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Shared\ValidationException;

/**
 * Cas d'usage des tournois : lectures publiques, inscriptions des membres et
 * administration journalisée. Chaque écriture se fait dans une transaction,
 * journal compris ; chaque réponse charge les membres une seule fois.
 */
final class TournamentService
{
    private const GUEST = 'Un compte invité ne peut pas s\'inscrire à un tournoi.';

    public function __construct(
        private readonly TournamentRepository $tournaments,
        private readonly Access $access,
        private readonly Members $members,
        private readonly AdminJournal $journal,
        private readonly Clock $clock,
        private readonly TransactionRunner $transactions,
        private readonly TournamentViews $views,
        private readonly PairingGames $games,
    ) {}

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return $this->views->summaries($this->tournaments->summaries());
    }

    /** @return array<string, mixed> */
    public function get(string $id): array
    {
        return $this->view($this->tournaments->get($id), $this->access->viewer());
    }

    /** @return array<string, mixed> */
    public function register(string $id): array
    {
        $member = $this->player();

        return $this->transactions->run(function () use ($id, $member): array {
            $t = $this->tournaments->getForUpdate($id);
            $t->register($member->id);
            $this->tournaments->save($t);

            return $this->view($t, $member);
        });
    }

    /** @return array<string, mixed> */
    public function unregister(string $id): array
    {
        $member = $this->player();

        return $this->transactions->run(function () use ($id, $member): array {
            $t = $this->tournaments->getForUpdate($id);
            $t->unregister($member->id);
            $this->tournaments->save($t);

            return $this->view($t, $member);
        });
    }

    /**
     * @param array<mixed>|null $timeControl
     * @return array<string, mixed>
     */
    public function create(string $name, string $description, string $format, ?array $timeControl, ?int $rounds): array
    {
        $admin = $this->access->admin();
        $tournamentFormat = TournamentFormat::tryFrom($format) ?? throw new ValidationException('Format de tournoi inconnu.');
        $tc = TimeControl::fromArray($timeControl);

        return $this->transactions->run(function () use ($admin, $name, $description, $tournamentFormat, $tc, $rounds): array {
            // Construit d'abord : le nom comparé est le nom nettoyé et validé.
            $t = Tournament::create($this->tournaments->nextId(), $name, $description, $tournamentFormat, $tc, $rounds, $admin->id, $this->clock->now());
            if ($this->tournaments->nameTaken($t->name())) {
                throw new ConflictException('Ce nom de tournoi est déjà pris.');
            }
            $this->tournaments->add($t);
            $this->journal->record($admin->id, 'Création du tournoi « ' . $t->name() . ' »');

            return $this->view($t, $admin);
        });
    }

    /** @return array<string, mixed> */
    public function setRegistration(string $id, bool $open): array
    {
        return $this->administer($id, static function (Tournament $t) use ($open): string {
            $t->setRegistrationOpen($open);

            return 'Tournoi « ' . $t->name() . ' » : inscriptions ' . ($open ? 'ouvertes' : 'fermées');
        });
    }

    /** @return array<string, mixed> */
    public function start(string $id): array
    {
        return $this->administer($id, function (Tournament $t, array $pseudos): string {
            // Seul un tournoi encore en inscriptions est concerné ; sinon l'agrégat dit pourquoi.
            if ($t->status() === TournamentStatus::Registration && $this->tournaments->runningExists($t->id())) {
                throw new ConflictException('Un autre tournoi est déjà en cours (un seul à la fois).');
            }
            $t->start($pseudos, $this->clock->now());
            $this->createGames($t);

            return 'Lancement du tournoi « ' . $t->name() . ' » (' . \count($t->players()) . ' joueurs)';
        });
    }

    /** @return array<string, mixed> */
    public function setResult(string $id, int $round, int $board, ?string $result): array
    {
        return $this->administer($id, static function (Tournament $t, array $pseudos, array $members) use ($round, $board, $result): string {
            $parsed = $result === null ? null : (Result::tryFrom($result) ?? throw new ValidationException('Résultat inconnu.'));
            $p = $t->setResult($round, $board, $parsed);
            $name = static fn(?string $playerId): string => $playerId === null ? '' : MemberRef::resolve($playerId, $members)->pseudo;

            return 'Tournoi « ' . $t->name() . ' » : R' . $round . ' ' . $name($p->whiteId) . '–' . $name($p->blackId) . ' = ' . ($parsed->value ?? 'effacé');
        });
    }

    /** @return array<string, mixed> */
    public function nextRound(string $id, bool $force): array
    {
        return $this->administer($id, function (Tournament $t, array $pseudos) use ($force): string {
            $t->nextRound($force, $pseudos);
            $this->createGames($t);

            return 'Tournoi « ' . $t->name() . ' » : passage à la ronde ' . $t->currentRound();
        });
    }

    /** @return array<string, mixed> */
    public function close(string $id): array
    {
        return $this->administer($id, function (Tournament $t): string {
            $t->close($this->clock->now());

            return 'Clôture du tournoi « ' . $t->name() . ' »';
        });
    }

    /** Report automatique d'une partie terminée ; sans effet si elle n'appartient à aucun tournoi en cours. */
    public function recordGameResult(string $gameId, Result $result): void
    {
        $this->transactions->run(function () use ($gameId, $result): void {
            $t = $this->tournaments->findByGameId($gameId);
            if ($t !== null && $t->recordGameResult($gameId, $result)) {
                $this->tournaments->save($t);
            }
        });
    }

    public function delete(string $id): void
    {
        $admin = $this->access->admin();
        $this->transactions->run(function () use ($admin, $id): void {
            $t = $this->tournaments->getForUpdate($id);
            $this->tournaments->delete($t->id());
            $this->journal->record($admin->id, 'Suppression du tournoi « ' . $t->name() . ' »');
        });
    }

    /**
     * Action d'administration sur un tournoi existant : contrôle admin, verrou,
     * modification, enregistrement et journal dans la même transaction.
     * $change modifie le tournoi et rend la phrase du journal ; il reçoit les
     * pseudos (id => pseudo) et les membres chargés.
     *
     * @param callable(Tournament, array<string, string>, array<string, Member>): string $change
     * @return array<string, mixed>
     */
    private function administer(string $id, callable $change): array
    {
        $admin = $this->access->admin();

        return $this->transactions->run(function () use ($admin, $id, $change): array {
            $t = $this->tournaments->getForUpdate($id);
            // Les rencontres ne font intervenir que des inscrits : la carte chargée avant la modification suffit après.
            $members = $this->members->findMany(TournamentViews::memberIds($t));
            $sentence = $change($t, array_map(static fn(Member $m): string => $m->pseudo, $members), $members);
            $this->tournaments->save($t);
            $this->journal->record($admin->id, $sentence);

            return $this->views->tournament($t, $admin, $members);
        });
    }

    /** Membre actif et non invité : seul un vrai compte s'inscrit à un tournoi. */
    private function player(): Member
    {
        $member = $this->access->member();

        return $member->guest ? throw new ForbiddenException(self::GUEST) : $member;
    }

    /** @return array<string, mixed> */
    private function view(Tournament $t, ?Member $viewer): array
    {
        return $this->views->tournament($t, $viewer, $this->members->findMany(TournamentViews::memberIds($t)));
    }

    private function createGames(Tournament $t): void
    {
        foreach ($t->pairingsWithoutGame() as $p) {
            $t->linkGame($p->board, $this->games->create((string) $p->whiteId, (string) $p->blackId, $t->timeControl()));
        }
    }
}
