<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveFlag;
use LeCercle\Chess\Position;
use LeCercle\Chess\Result;
use LeCercle\Chess\Square;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\TimeControl;
use LeCercle\Shared\ValidationException;

/** Agrégat Partie : sièges, coups joués, pendule et fin de partie ; la position se déduit des coups. */
final class Game
{
    private Position $position;

    /** @var list<string> clé de répétition de chaque position, la position initiale comprise */
    private array $repetitionKeys;

    /** @var array{w: list<string>, b: list<string>} lettres FEN des pièces prises par chaque camp */
    private array $captured;

    /** @var list<PlayedMove> */
    private array $moves = [];

    private function __construct(
        private readonly string $id,
        private readonly GameMode $mode,
        private readonly string $ownerId,
        private ?string $whiteId,
        private ?string $blackId,
        private readonly ?AiLevel $level,
        private readonly ?TimeControl $timeControl,
        private GameStatus $status,
        private ?GameClock $clock,
        private ?Color $drawOffer,
        private ?Result $result,
        private ?FinishReason $reason,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->resetBoard();
    }

    /** Partie locale : le propriétaire tient les deux camps. */
    public static function local(string $id, string $ownerId, ?TimeControl $tc, \DateTimeImmutable $now): self
    {
        return self::started($id, GameMode::Local, $ownerId, $ownerId, null, $tc, $now);
    }

    /** Partie contre l'ordinateur : le propriétaire a les blancs. */
    public static function againstComputer(string $id, string $ownerId, AiLevel $level, ?TimeControl $tc, \DateTimeImmutable $now): self
    {
        return self::started($id, GameMode::Computer, $ownerId, null, $level, $tc, $now);
    }

    /** Partie en ligne : le créateur prend la couleur choisie et attend un adversaire. */
    public static function online(string $id, string $creatorId, Color $creatorColor, ?TimeControl $tc, \DateTimeImmutable $now): self
    {
        return new self(
            $id,
            GameMode::Online,
            $creatorId,
            $creatorColor === Color::White ? $creatorId : null,
            $creatorColor === Color::Black ? $creatorId : null,
            null,
            $tc,
            GameStatus::Open,
            $tc === null ? null : GameClock::start($tc),
            null,
            null,
            null,
            $now,
            $now,
        );
    }

    /** Partie d'un appariement de tournoi : en cours, deux sièges pris, pendule arrêtée jusqu'au premier coup des blancs. */
    public static function forTournament(string $id, string $whiteId, string $blackId, ?TimeControl $tc, \DateTimeImmutable $now): self
    {
        return new self(
            $id,
            GameMode::Online,
            $whiteId,
            $whiteId,
            $blackId,
            null,
            $tc,
            GameStatus::Playing,
            $tc === null ? null : GameClock::start($tc),
            null,
            null,
            null,
            $now,
            $now,
        );
    }

    /** Reconstruit une partie stockée en rejouant ses coups depuis la position initiale. */
    public static function restore(GameSnapshot $s, ChessRules $rules): self
    {
        $game = new self(
            $s->id,
            $s->mode,
            $s->ownerId,
            $s->whiteId,
            $s->blackId,
            $s->level,
            $s->timeControl,
            $s->status,
            $s->clock,
            $s->drawOffer,
            $s->result,
            $s->reason,
            $s->createdAt,
            $s->updatedAt,
        );
        $game->replay($s->moves, $rules);

        return $game;
    }

    public function snapshot(): GameSnapshot
    {
        return new GameSnapshot(
            $this->id,
            $this->mode,
            $this->ownerId,
            $this->whiteId,
            $this->blackId,
            $this->level,
            $this->timeControl,
            $this->status,
            $this->moves,
            $this->clock,
            $this->drawOffer,
            $this->result,
            $this->reason,
            $this->createdAt,
            $this->updatedAt,
        );
    }

    /** @return list<Color> */
    public function colorsOf(?string $userId): array
    {
        if ($userId === null) {
            return [];
        }
        $colors = [];
        if ($this->whiteId === $userId) {
            $colors[] = Color::White;
        }
        if ($this->blackId === $userId) {
            $colors[] = Color::Black;
        }

        return $colors;
    }

    public function play(string $userId, string $uci, ChessRules $rules, \DateTimeImmutable $now): void
    {
        if ($this->status !== GameStatus::Playing) {
            throw new ConflictException('La partie n’est pas en cours.');
        }
        $colors = $this->colorsOf($userId);
        if ($colors === []) {
            throw new ForbiddenException('Tu ne joues pas cette partie.');
        }
        if (!\in_array($this->position->sideToMove(), $colors, true)) {
            throw new ConflictException('Ce n’est pas ton tour.');
        }
        $this->apply($rules->moveFromUci($this->position, $uci), $rules, $now);
    }

    /** Joue le coup choisi par l'ordinateur ; il est revalidé contre la position courante. */
    public function playComputer(Move $move, ChessRules $rules, \DateTimeImmutable $now): void
    {
        if (!$this->computerToMove()) {
            throw new \LogicException("L'ordinateur n'a pas le trait dans la partie {$this->id}.");
        }
        $this->apply($rules->moveFromUci($this->position, $move->toUci()), $rules, $now);
    }

    /** Prend le siège libre et lance la partie : la pendule des blancs part maintenant. Sans effet pour un joueur de la partie. */
    public function join(string $userId, \DateTimeImmutable $now): void
    {
        if ($this->colorsOf($userId) !== []) {
            return;
        }
        if ($this->status !== GameStatus::Open) {
            throw new ConflictException('Cette partie a déjà commencé.');
        }
        if ($this->whiteId === null) {
            $this->whiteId = $userId;
        } else {
            $this->blackId = $userId;
        }
        $this->status = GameStatus::Playing;
        $this->clock = $this->clock?->runFor(Color::White, $now);
        $this->updatedAt = $now;
    }

    public function resign(string $userId, \DateTimeImmutable $now): void
    {
        $color = $this->onlinePlayerColor($userId, 'Abandon réservé aux parties en ligne.');
        $this->finish(self::winOf($color->opponent()), FinishReason::Resignation, $now);
    }

    public function offerDraw(string $userId, \DateTimeImmutable $now): void
    {
        $this->drawOffer = $this->onlinePlayerColor($userId, 'Nulle par accord réservée aux parties en ligne.');
        $this->updatedAt = $now;
    }

    public function acceptDraw(string $userId, \DateTimeImmutable $now): void
    {
        $color = $this->onlinePlayerColor($userId, 'Nulle par accord réservée aux parties en ligne.');
        if ($this->drawOffer !== $color->opponent()) {
            throw new ConflictException('Aucune proposition de nulle à accepter.');
        }
        $this->finish(Result::Draw, FinishReason::Agreement, $now);
    }

    /** Décision administrative : la partie se termine avec le résultat donné. */
    public function closeByAdmin(Result $result, \DateTimeImmutable $now): void
    {
        if ($this->status === GameStatus::Finished) {
            throw new ConflictException('La partie est déjà terminée.');
        }
        $this->finish($result, FinishReason::Admin, $now);
    }

    /** Termine la partie si le camp au trait n'a plus de temps ; vrai si cet appel l'a terminée. */
    public function checkFlag(\DateTimeImmutable $now): bool
    {
        $flagged = $this->status === GameStatus::Playing ? $this->clock?->flagged($now) : null;
        if ($flagged === null) {
            return false;
        }
        $this->finish(self::winOf($flagged->opponent()), FinishReason::Time, $now);

        return true;
    }

    /**
     * Annule le dernier coup d'une partie locale (port de btnLocalUndo) ; contre l'ordinateur,
     * annule aussi sa réponse pour rendre le trait au propriétaire.
     */
    public function undo(string $userId, ChessRules $rules, \DateTimeImmutable $now): void
    {
        if ($this->mode === GameMode::Online) {
            throw new ConflictException('Annulation réservée aux parties locales.');
        }
        if ($userId !== $this->ownerId) {
            throw new ForbiddenException('Tu ne joues pas cette partie.');
        }
        if ($this->moves === []) {
            throw new ConflictException('Aucun coup à annuler.');
        }
        $remaining = \array_slice($this->moves, 0, -1);
        // Nombre de coups impair : les noirs, donc l'ordinateur, auraient le trait.
        if ($this->mode === GameMode::Computer && \count($remaining) % 2 === 1) {
            $remaining = \array_slice($remaining, 0, -1);
        }
        $this->replay($remaining, $rules);
        $this->status = GameStatus::Playing;
        $this->result = null;
        $this->reason = null;
        $this->drawOffer = null;
        $this->clock = $this->clockAfterUndo($now);
        $this->updatedAt = $now;
    }

    public function computerToMove(): bool
    {
        return $this->mode === GameMode::Computer
            && $this->status === GameStatus::Playing
            && $this->position->sideToMove() === Color::Black;
    }

    public function plyCount(): int
    {
        return \count($this->moves);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function mode(): GameMode
    {
        return $this->mode;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function whiteId(): ?string
    {
        return $this->whiteId;
    }

    public function blackId(): ?string
    {
        return $this->blackId;
    }

    public function level(): ?AiLevel
    {
        return $this->level;
    }

    public function timeControl(): ?TimeControl
    {
        return $this->timeControl;
    }

    public function status(): GameStatus
    {
        return $this->status;
    }

    public function position(): Position
    {
        return $this->position;
    }

    /** @return list<PlayedMove> */
    public function moves(): array
    {
        return $this->moves;
    }

    /** @return array{w: list<string>, b: list<string>} */
    public function captured(): array
    {
        return $this->captured;
    }

    public function clock(): ?GameClock
    {
        return $this->clock;
    }

    public function drawOffer(): ?Color
    {
        return $this->drawOffer;
    }

    public function result(): ?Result
    {
        return $this->result;
    }

    public function reason(): ?FinishReason
    {
        return $this->reason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** Partie en cours dès sa création ; la pendule n'existe que si une cadence est donnée. */
    private static function started(
        string $id,
        GameMode $mode,
        string $ownerId,
        ?string $blackId,
        ?AiLevel $level,
        ?TimeControl $tc,
        \DateTimeImmutable $now,
    ): self {
        return new self(
            $id,
            $mode,
            $ownerId,
            $ownerId,
            $blackId,
            $level,
            $tc,
            GameStatus::Playing,
            $tc === null ? null : GameClock::start($tc),
            null,
            null,
            null,
            $now,
            $now,
        );
    }

    /** Coup légal joué maintenant : notation, pendule, offre de nulle retirée puis détection de la fin. */
    private function apply(Move $move, ChessRules $rules, \DateTimeImmutable $now): void
    {
        $mover = $this->position->sideToMove();
        $san = $rules->san($this->position, $move);
        $this->advance($move);
        $this->clock = $this->clock?->afterMove($mover, $now);
        $this->moves[] = new PlayedMove($move->toUci(), $san, $this->clock?->whiteMs, $this->clock?->blackMs, $now);
        $this->drawOffer = null;
        $this->updatedAt = $now;
        $outcome = $rules->outcome($this->position, $this->repetitionKeys);
        if ($outcome !== null) {
            $this->finish($outcome->result, FinishReason::fromEndReason($outcome->reason), $now);
        }
    }

    /** Couleur du joueur $userId dans une partie en ligne en cours ; sinon l'erreur adaptée. */
    private function onlinePlayerColor(string $userId, string $notOnlineMessage): Color
    {
        if ($this->mode !== GameMode::Online) {
            throw new ConflictException($notOnlineMessage);
        }
        if ($this->status !== GameStatus::Playing) {
            throw new ConflictException('La partie n’est pas en cours.');
        }
        $colors = $this->colorsOf($userId);
        if ($colors === []) {
            throw new ForbiddenException('Tu ne joues pas cette partie.');
        }

        return $colors[0];
    }

    private static function winOf(Color $winner): Result
    {
        return $winner === Color::White ? Result::WhiteWins : Result::BlackWins;
    }

    /** Sans coup : temps complets, pendule arrêtée ; sinon les temps du dernier coup restant, le camp au trait partant de $now. */
    private function clockAfterUndo(\DateTimeImmutable $now): ?GameClock
    {
        if ($this->timeControl === null) {
            return null;
        }
        $full = GameClock::start($this->timeControl);
        $last = $this->moves[\count($this->moves) - 1] ?? null;
        if ($last === null) {
            return $full;
        }
        if ($last->whiteMs === null || $last->blackMs === null) {
            throw new \UnexpectedValueException('Partie ' . $this->id . ' : temps manquants au coup ' . \count($this->moves) . '.');
        }

        return $full->withTimes($last->whiteMs, $last->blackMs, $this->position->sideToMove(), $now);
    }

    private function finish(Result $result, FinishReason $reason, \DateTimeImmutable $now): void
    {
        $this->status = GameStatus::Finished;
        $this->result = $result;
        $this->reason = $reason;
        $this->clock = $this->clock?->stopped($now);
        $this->drawOffer = null;
        $this->updatedAt = $now;
    }

    /**
     * Rejoue des coups stockés depuis la position initiale, sans toucher pendule ni statut.
     *
     * @param list<PlayedMove> $moves
     */
    private function replay(array $moves, ChessRules $rules): void
    {
        $this->resetBoard();
        $this->moves = [];
        foreach ($moves as $i => $played) {
            try {
                $move = $rules->moveFromUci($this->position, $played->uci);
            } catch (ValidationException $e) {
                throw new \UnexpectedValueException('Partie ' . $this->id . ' corrompue au coup ' . ($i + 1) . '.', 0, $e);
            }
            $this->advance($move);
            $this->moves[] = $played;
        }
    }

    /** Position initiale, sans prise, la clé de la position initiale comptant pour la répétition. */
    private function resetBoard(): void
    {
        $this->position = Position::initial();
        $this->repetitionKeys = [$this->position->repetitionKey()];
        $this->captured = [Color::White->value => [], Color::Black->value => []];
    }

    /** Pièce prise (pion adverse pour la prise en passant), nouvelle position et sa clé de répétition. */
    private function advance(Move $move): void
    {
        $mover = $this->position->sideToMove();
        $taken = $move->flag === MoveFlag::EnPassant
            ? $this->position->pieceAt(Square::at($move->to->file(), $move->from->rank()))
            : $this->position->pieceAt($move->to);
        if ($taken !== null) {
            $this->captured[$mover->value][] = $taken->toFenChar();
        }
        $this->position = $this->position->apply($move);
        $this->repetitionKeys[] = $this->position->repetitionKey();
    }
}
