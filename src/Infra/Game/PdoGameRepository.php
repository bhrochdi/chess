<?php

declare(strict_types=1);

namespace LeCercle\Infra\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Game\FinishReason;
use LeCercle\Game\Game;
use LeCercle\Game\GameClock;
use LeCercle\Game\GameMode;
use LeCercle\Game\GameRepository;
use LeCercle\Game\GameSnapshot;
use LeCercle\Game\GameStatus;
use LeCercle\Game\GameSummary;
use LeCercle\Game\PlayedMove;
use LeCercle\Infra\Db\SqlTime;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;

/** Parties stockées dans les tables `games` et `game_moves` ; l'appelant ouvre la transaction. */
final class PdoGameRepository implements GameRepository
{
    private const COLUMNS = 'id, mode, status, owner_id, white_id, black_id, ai_level, tc_base, tc_inc, white_ms, black_ms,'
        . ' clock_running, clock_since, draw_offer, result, reason, created_at, updated_at';

    public function __construct(private readonly \PDO $pdo, private readonly ChessRules $rules) {}

    public function nextId(): string
    {
        return 'g' . bin2hex(random_bytes(6));
    }

    public function add(Game $game): void
    {
        $s = $game->snapshot();
        $stmt = $this->pdo->prepare(
            'INSERT INTO games (' . self::COLUMNS . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([
            $s->id,
            $s->mode->value,
            $s->status->value,
            $s->ownerId,
            $s->whiteId,
            $s->blackId,
            $s->level?->value,
            $s->timeControl?->baseSeconds,
            $s->timeControl?->incrementSeconds,
            $s->clock?->whiteMs,
            $s->clock?->blackMs,
            $s->clock?->running?->value,
            $s->clock?->since === null ? null : SqlTime::format($s->clock->since),
            $s->drawOffer?->value,
            $s->result?->value,
            $s->reason?->value,
            SqlTime::format($s->createdAt),
            SqlTime::format($s->updatedAt),
        ]);
        $this->insertMoves($s->id, $s->moves, 0);
    }

    public function get(string $id): Game
    {
        return $this->load($id, '');
    }

    public function getForUpdate(string $id): Game
    {
        return $this->load($id, ' FOR UPDATE');
    }

    public function save(Game $game): void
    {
        $s = $game->snapshot();
        $stmt = $this->pdo->prepare(
            'UPDATE games SET status = ?, white_id = ?, black_id = ?, white_ms = ?, black_ms = ?, clock_running = ?,'
            . ' clock_since = ?, draw_offer = ?, result = ?, reason = ?, updated_at = ? WHERE id = ?',
        );
        $stmt->execute([
            $s->status->value,
            $s->whiteId,
            $s->blackId,
            $s->clock?->whiteMs,
            $s->clock?->blackMs,
            $s->clock?->running?->value,
            $s->clock?->since === null ? null : SqlTime::format($s->clock->since),
            $s->drawOffer?->value,
            $s->result?->value,
            $s->reason?->value,
            SqlTime::format($s->updatedAt),
            $s->id,
        ]);

        // Après une annulation, la partie compte moins de coups qu'en base.
        $delete = $this->pdo->prepare('DELETE FROM game_moves WHERE game_id = ? AND ply > ?');
        $delete->execute([$s->id, \count($s->moves)]);

        $max = $this->pdo->prepare('SELECT COALESCE(MAX(ply), 0) FROM game_moves WHERE game_id = ?');
        $max->execute([$s->id]);
        $this->insertMoves($s->id, $s->moves, (int) $max->fetchColumn());
    }

    public function delete(string $id): void
    {
        // Les coups partent par la clé étrangère ; commentaires et lien de tournoi n'en ont pas.
        $this->pdo->prepare('DELETE FROM game_comments WHERE game_id = ?')->execute([$id]);
        $this->pdo->prepare('UPDATE tournament_pairings SET game_id = NULL WHERE game_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM games WHERE id = ?')->execute([$id]);
    }

    public function finishedIds(): array
    {
        $stmt = $this->pdo->prepare("SELECT id FROM games WHERE status = 'finished' ORDER BY id");
        $stmt->execute();

        return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function all(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM games ORDER BY updated_at DESC, id LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);

        return $this->summaries($stmt);
    }

    public function lobby(\DateTimeImmutable $finishedSince, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . " FROM games WHERE mode = 'online'"
            . " AND (status IN ('open', 'playing') OR (status = 'finished' AND updated_at >= ?))"
            . ' ORDER BY updated_at DESC, id LIMIT ?',
        );
        $stmt->bindValue(1, SqlTime::format($finishedSince));
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);

        return $this->summaries($stmt);
    }

    public function unfinishedOf(string $userId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . ' FROM games WHERE (owner_id = ? OR white_id = ? OR black_id = ?)'
            . " AND status <> 'finished' ORDER BY updated_at DESC, id LIMIT ?",
        );
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $userId);
        $stmt->bindValue(3, $userId);
        $stmt->bindValue(4, $limit, \PDO::PARAM_INT);

        return $this->summaries($stmt);
    }

    /** @param ''|' FOR UPDATE' $lock */
    private function load(string $id, string $lock): Game
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM games WHERE id = ?' . $lock);
        $stmt->execute([$id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new NotFoundException('Partie introuvable.');
        }

        $movesStmt = $this->pdo->prepare(
            'SELECT uci, san, white_ms, black_ms, played_at FROM game_moves WHERE game_id = ? ORDER BY ply',
        );
        $movesStmt->execute([$id]);
        $moves = [];
        foreach ($movesStmt->fetchAll(\PDO::FETCH_ASSOC) as $m) {
            $moves[] = new PlayedMove(
                (string) $m['uci'],
                (string) $m['san'],
                $m['white_ms'] === null ? null : (int) $m['white_ms'],
                $m['black_ms'] === null ? null : (int) $m['black_ms'],
                SqlTime::parse((string) $m['played_at']),
            );
        }

        return Game::restore($this->snapshotOf($row, $moves), $this->rules);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<PlayedMove>     $moves
     */
    private function snapshotOf(array $row, array $moves): GameSnapshot
    {
        $timeControl = $this->timeControlOf($row);
        $clock = null;
        if ($row['white_ms'] !== null && $row['black_ms'] !== null) {
            $clock = new GameClock(
                (int) $row['white_ms'],
                (int) $row['black_ms'],
                ($timeControl->incrementSeconds ?? 0) * 1000,
                $row['clock_running'] === null ? null : Color::from((string) $row['clock_running']),
                $row['clock_since'] === null ? null : SqlTime::parse((string) $row['clock_since']),
            );
        }

        return new GameSnapshot(
            (string) $row['id'],
            GameMode::from((string) $row['mode']),
            (string) $row['owner_id'],
            $row['white_id'] === null ? null : (string) $row['white_id'],
            $row['black_id'] === null ? null : (string) $row['black_id'],
            $row['ai_level'] === null ? null : AiLevel::from((string) $row['ai_level']),
            $timeControl,
            GameStatus::from((string) $row['status']),
            $moves,
            $clock,
            $row['draw_offer'] === null ? null : Color::from((string) $row['draw_offer']),
            $row['result'] === null ? null : Result::from((string) $row['result']),
            $row['reason'] === null ? null : FinishReason::from((string) $row['reason']),
            SqlTime::parse((string) $row['created_at']),
            SqlTime::parse((string) $row['updated_at']),
        );
    }

    /** @param array<string, mixed> $row */
    private function timeControlOf(array $row): ?TimeControl
    {
        if ($row['tc_base'] === null || $row['tc_inc'] === null) {
            return null;
        }

        return new TimeControl((int) $row['tc_base'], (int) $row['tc_inc']);
    }

    /**
     * Insère les coups au-delà du rang $stored (le coup numéro $i va au rang $i + 1).
     *
     * @param list<PlayedMove> $moves
     */
    private function insertMoves(string $gameId, array $moves, int $stored): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO game_moves (game_id, ply, uci, san, white_ms, black_ms, played_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        foreach (\array_slice($moves, $stored, null, true) as $i => $move) {
            $stmt->execute([
                $gameId,
                $i + 1,
                $move->uci,
                $move->san,
                $move->whiteMs,
                $move->blackMs,
                SqlTime::format($move->playedAt),
            ]);
        }
    }

    /** @return list<GameSummary> */
    private function summaries(\PDOStatement $stmt): array
    {
        $stmt->execute();
        $summaries = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $snapshot = $this->snapshotOf($row, []);
            $summaries[] = new GameSummary(
                $snapshot->id,
                $snapshot->mode,
                $snapshot->status,
                $snapshot->ownerId,
                $snapshot->whiteId,
                $snapshot->blackId,
                $snapshot->level,
                $snapshot->timeControl,
                $snapshot->result,
                $snapshot->reason,
                $snapshot->updatedAt,
            );
        }

        return $summaries;
    }
}
