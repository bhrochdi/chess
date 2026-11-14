<?php

declare(strict_types=1);

namespace LeCercle\Infra\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Infra\Db\SqlTime;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentFormat;
use LeCercle\Tournament\TournamentRepository;
use LeCercle\Tournament\TournamentSnapshot;
use LeCercle\Tournament\TournamentStatus;
use LeCercle\Tournament\TournamentSummary;

/** Tournois stockés dans `tournaments`, `tournament_players` et `tournament_pairings`. */
final class PdoTournamentRepository implements TournamentRepository
{
    private const COLUMNS = 'id, name, description, format, tc_base, tc_inc, status, registration_open, planned_rounds, current_round, created_by, created_at, started_at, finished_at';

    public function __construct(private readonly \PDO $pdo) {}

    public function nextId(): string
    {
        return 't' . bin2hex(random_bytes(6));
    }

    public function add(Tournament $t): void
    {
        $this->guardUniqueName(function () use ($t): void {
            $this->pdo->prepare(
                'INSERT INTO tournaments (' . self::COLUMNS . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            )->execute([
                $t->id(),
                $t->name(),
                $t->description(),
                $t->format()->value,
                $t->timeControl()?->baseSeconds,
                $t->timeControl()?->incrementSeconds,
                $t->status()->value,
                (int) $t->registrationOpen(),
                $t->plannedRounds(),
                $t->currentRound(),
                $t->createdBy(),
                SqlTime::format($t->createdAt()),
                $t->startedAt() === null ? null : SqlTime::format($t->startedAt()),
                $t->finishedAt() === null ? null : SqlTime::format($t->finishedAt()),
            ]);
        });
        $this->insertChildren($t);
    }

    public function get(string $id): Tournament
    {
        return $this->load($id, '');
    }

    public function getForUpdate(string $id): Tournament
    {
        return $this->load($id, ' FOR UPDATE');
    }

    public function findByGameId(string $gameId): ?Tournament
    {
        $stmt = $this->pdo->prepare('SELECT tournament_id FROM tournament_pairings WHERE game_id = ? LIMIT 1');
        $stmt->execute([$gameId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : $this->load((string) $id, ' FOR UPDATE');
    }

    public function save(Tournament $t): void
    {
        $this->guardUniqueName(function () use ($t): void {
            $this->pdo->prepare(
                'UPDATE tournaments SET name = ?, description = ?, status = ?, registration_open = ?, planned_rounds = ?,
                 current_round = ?, started_at = ?, finished_at = ? WHERE id = ?',
            )->execute([
                $t->name(),
                $t->description(),
                $t->status()->value,
                (int) $t->registrationOpen(),
                $t->plannedRounds(),
                $t->currentRound(),
                $t->startedAt() === null ? null : SqlTime::format($t->startedAt()),
                $t->finishedAt() === null ? null : SqlTime::format($t->finishedAt()),
                $t->id(),
            ]);
        });
        $this->pdo->prepare('DELETE FROM tournament_players WHERE tournament_id = ?')->execute([$t->id()]);
        $this->pdo->prepare('DELETE FROM tournament_pairings WHERE tournament_id = ?')->execute([$t->id()]);
        $this->insertChildren($t);
    }

    public function delete(string $id): void
    {
        // Les clés étrangères (ON DELETE CASCADE) purgent inscrits et appariements.
        $this->pdo->prepare('DELETE FROM tournaments WHERE id = ?')->execute([$id]);
    }

    public function summaries(): array
    {
        $stmt = $this->pdo->query(
            'SELECT t.id, t.name, t.format, t.tc_base, t.tc_inc, t.status, t.started_at, t.created_at, COALESCE(p.n, 0) AS player_count
             FROM tournaments t
             LEFT JOIN (SELECT tournament_id, COUNT(*) AS n FROM tournament_players GROUP BY tournament_id) p ON p.tournament_id = t.id
             ORDER BY t.created_at, t.id',
        );
        \assert($stmt instanceof \PDOStatement);

        return array_values(array_map(
            static fn(array $row): TournamentSummary => new TournamentSummary(
                (string) $row['id'],
                (string) $row['name'],
                TournamentFormat::from((string) $row['format']),
                self::timeControl($row),
                TournamentStatus::from((string) $row['status']),
                (int) $row['player_count'],
                $row['started_at'] === null ? null : SqlTime::parse((string) $row['started_at']),
                SqlTime::parse((string) $row['created_at']),
            ),
            $stmt->fetchAll(),
        ));
    }

    public function nameTaken(string $name, ?string $exceptId = null): bool
    {
        // Sous utf8mb4_unicode_ci, la comparaison ignore aussi les accents et les espaces finaux.
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tournaments WHERE LOWER(name) = LOWER(?) AND id <> ?');
        $stmt->execute([$name, $exceptId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function runningExists(?string $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM tournaments WHERE status = ? AND id <> ?');
        $stmt->execute([TournamentStatus::Running->value, $exceptId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function load(string $id, string $lock): Tournament
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM tournaments WHERE id = ?' . $lock);
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!\is_array($row)) {
            throw new NotFoundException('Tournoi introuvable.');
        }

        $stmt = $this->pdo->prepare('SELECT user_id FROM tournament_players WHERE tournament_id = ? ORDER BY seq');
        $stmt->execute([$id]);
        $players = array_values(array_map(strval(...), $stmt->fetchAll(\PDO::FETCH_COLUMN)));

        $stmt = $this->pdo->prepare(
            'SELECT round, board, white_id, black_id, result, game_id FROM tournament_pairings WHERE tournament_id = ? ORDER BY round, board',
        );
        $stmt->execute([$id]);
        /** @var array<int, list<Pairing>> $byRound */
        $byRound = [];
        foreach ($stmt->fetchAll() as $p) {
            $byRound[(int) $p['round']][] = new Pairing(
                (int) $p['board'],
                $p['white_id'] === null ? null : (string) $p['white_id'],
                $p['black_id'] === null ? null : (string) $p['black_id'],
                $p['result'] === null ? null : Result::from((string) $p['result']),
                $p['game_id'] === null ? null : (string) $p['game_id'],
            );
        }
        $rounds = [];
        foreach ($byRound as $number => $pairings) {
            $rounds[] = new Round($number, $pairings);
        }

        return Tournament::restore(new TournamentSnapshot(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['description'],
            TournamentFormat::from((string) $row['format']),
            self::timeControl($row),
            TournamentStatus::from((string) $row['status']),
            (int) $row['registration_open'] === 1,
            $row['planned_rounds'] === null ? null : (int) $row['planned_rounds'],
            (int) $row['current_round'],
            $players,
            $rounds,
            (string) $row['created_by'],
            SqlTime::parse((string) $row['created_at']),
            $row['started_at'] === null ? null : SqlTime::parse((string) $row['started_at']),
            $row['finished_at'] === null ? null : SqlTime::parse((string) $row['finished_at']),
        ));
    }

    private function insertChildren(Tournament $t): void
    {
        $player = $this->pdo->prepare('INSERT INTO tournament_players (tournament_id, user_id, seq) VALUES (?, ?, ?)');
        foreach ($t->players() as $seq => $userId) {
            $player->execute([$t->id(), $userId, $seq]);
        }
        $pairing = $this->pdo->prepare(
            'INSERT INTO tournament_pairings (tournament_id, round, board, white_id, black_id, result, game_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($t->rounds() as $round) {
            foreach ($round->pairings as $p) {
                $pairing->execute([$t->id(), $round->number, $p->board, $p->whiteId, $p->blackId, $p->result?->value, $p->gameId]);
            }
        }
    }

    /** @param array<array-key, mixed> $row */
    private static function timeControl(array $row): ?TimeControl
    {
        return $row['tc_base'] === null ? null : new TimeControl((int) $row['tc_base'], (int) ($row['tc_inc'] ?? 0));
    }

    private function guardUniqueName(\Closure $write): void
    {
        try {
            $write();
        } catch (\PDOException $e) {
            // Deux créations simultanées passent le contrôle du service : la clé unique du nom
            // tranche, avec la même réponse que nameTaken(). Les autres doublons restent des erreurs.
            if (
                $e->getCode() === '23000'
                && ($e->errorInfo[1] ?? null) === 1062
                && preg_match("/for key '(?:tournaments\\.)?uniq_tournaments_name'\\z/", (string) ($e->errorInfo[2] ?? '')) === 1
            ) {
                throw new ConflictException('Ce nom de tournoi est déjà pris.', 0, $e);
            }
            throw $e;
        }
    }
}
