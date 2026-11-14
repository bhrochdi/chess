# Domaine échecs (`src/Chess/`)

[Architecture](README.md) › Domaine échecs

| Classe | Responsabilité |
|--------|----------------|
| `Square`, `Color`, `PieceType`, `Piece` | Valeurs de base : case (0 à 63), camp, type et pièce. |
| `Move`, `MoveFlag` | Un coup (origine, destination, drapeau, promotion) et son format UCI. |
| `CastlingRights` | Droits de roque. |
| `Position` | Position immuable : lecture et écriture FEN, application d'un coup, clé de répétition. |
| `AttackDetector` | Dit si une case est attaquée et si un roi est en échec. |
| `MoveGenerator` | Coups légaux d'une position (vérifié par perft). |
| `BoardGeometry` | Interne : directions des pièces, rayons et sauts précalculés pour chaque case. |
| `Arbiter`, `Outcome`, `Result`, `EndReason` | Fin de partie : mat, pat, règle des 50 coups, triple répétition, matériel insuffisant. |
| `SanFormatter` | Notation algébrique abrégée (SAN). |
| `Ai\MoveChooser`, `Ai\MinimaxChooser` | Choix d'un coup : minimax avec élagage alpha-bêta. |
| `Ai\Evaluator`, `Ai\PieceSquareEvaluator` | Note d'une position : matériel plus tables de cases, repris du moteur JavaScript d'origine. |
| `Ai\PieceValues` | Interne : valeur des pièces, partagée par l'évaluation et l'ordre des captures. |
| `Ai\RandomSource`, `Ai\SystemRandom` | Source d'aléa injectée et son implémentation système. |
| `Ai\AiLevel` | Les trois niveaux proposés (`ai-2`, `ai-3`, `ai-4` : débutant, club, maître) : profondeur et bruit. |

Règle de dépendance : `src/Chess` ne fait aucune entrée-sortie (ni base, ni
session, ni horloge) et ne dépend que de lui-même. L'aléa de l'IA entre par
`RandomSource`.

Diagrammes de classes, en trois parties (méthodes publiques, telles qu'elles
sont dans le code). Position et valeurs de base :

```mermaid
classDiagram
    direction LR
    class Position {
        <<immuable>>
        +initial() Position$
        +fromFen(string fen) Position$
        +toFen() string
        +repetitionKey() string
        +pieceAt(Square s) Piece|null
        +sideToMove() Color
        +castling() CastlingRights
        +enPassant() Square|null
        +halfmoveClock() int
        +fullmoveNumber() int
        +kingSquare(Color c) Square|null
        +apply(Move m) Position
    }
    class Square {
        <<immuable>>
        +at(int file, int rank) Square$
        +fromIndex(int i) Square$
        +fromAlgebraic(string s) Square$
        +file() int
        +rank() int
        +index() int
        +toAlgebraic() string
        +offset(int df, int dr) Square|null
    }
    class Piece {
        <<immuable>>
        +PieceType type
        +Color color
        +fromFenChar(string c) Piece$
        +toFenChar() string
    }
    class Move {
        <<immuable>>
        +Square from
        +Square to
        +MoveFlag flag
        +PieceType|null promotion
        +toUci() string
        +equals(Move o) bool
    }
    class CastlingRights {
        <<immuable>>
        +fromFen(string s) CastlingRights$
        +toFen() string
        +withoutColor(Color c) CastlingRights
        +withoutRookSquare(Square s) CastlingRights
        +allows(Color c, MoveFlag side) bool
    }
    class Color {
        <<enumeration>>
        White = w
        Black = b
        +opponent() Color
    }
    class PieceType {
        <<enumeration>>
        Pawn = P
        Knight = N
        Bishop = B
        Rook = R
        Queen = Q
        King = K
    }
    class MoveFlag {
        <<enumeration>>
        Normal
        DoublePush
        EnPassant
        CastleKingside
        CastleQueenside
    }
    Position o-- "64" Piece : cases
    Position *-- CastlingRights
    Position ..> Move : apply
    Piece --> PieceType
    Piece --> Color
    Move --> Square
    Move --> MoveFlag
```

Règles : coups légaux, fin de partie, notation :

```mermaid
classDiagram
    direction LR
    class MoveGenerator {
        +legalMoves(Position p) list~Move~
        +legalMovesFrom(Position p, Square from) list~Move~
        +isInCheck(Position p) bool
    }
    class AttackDetector {
        +isAttacked(Position p, Square s, Color by) bool
        +isInCheck(Position p, Color c) bool
    }
    class BoardGeometry {
        <<interne>>
    }
    class Arbiter {
        +outcome(Position p, list~string~ repetitionKeys) Outcome|null
    }
    class SanFormatter {
        +format(Position before, Move move) string
    }
    class Outcome {
        <<immuable>>
        +Result result
        +EndReason reason
    }
    class Result {
        <<enumeration>>
        WhiteWins = 1-0
        BlackWins = 0-1
        Draw = 1/2-1/2
    }
    class EndReason {
        <<enumeration>>
        Checkmate = mat
        Stalemate = pat
        FiftyMoves = 50coups
        Threefold = repetition
        InsufficientMaterial = materiel
    }
    Arbiter --> MoveGenerator
    SanFormatter --> MoveGenerator
    MoveGenerator --> AttackDetector
    MoveGenerator ..> BoardGeometry
    AttackDetector ..> BoardGeometry
    Arbiter ..> Outcome
    Outcome --> Result
    Outcome --> EndReason
```

IA (`PieceValues`, interne, n'est pas dessiné) :

```mermaid
classDiagram
    direction LR
    class AiLevel {
        <<enumeration>>
        Beginner = ai-2
        Club = ai-3
        Master = ai-4
        +depth() int
        +fuzz() int
        +chooser(MoveGenerator m, RandomSource r) MoveChooser
    }
    class MinimaxChooser
    class MoveChooser {
        <<interface>>
        +choose(Position p) Move|null
    }
    class MoveGenerator
    class Evaluator {
        <<interface>>
        +evaluate(Position p) int
    }
    class PieceSquareEvaluator
    class RandomSource {
        <<interface>>
        +float() float
    }
    class SystemRandom
    AiLevel ..> MinimaxChooser : crée
    MinimaxChooser ..|> MoveChooser
    MinimaxChooser --> MoveGenerator
    MinimaxChooser --> Evaluator
    MinimaxChooser --> RandomSource
    Evaluator <|.. PieceSquareEvaluator
    RandomSource <|.. SystemRandom
```

`Ai\` regroupe `MoveChooser`, `Evaluator`, `RandomSource`, `MinimaxChooser`,
`PieceSquareEvaluator`, `PieceValues`, `SystemRandom` et `AiLevel` ; les autres
classes sont dans `LeCercle\Chess`.

## Performances de l'IA

Temps du choix d'un coup sur la position Kiwipete, sans bruit, médiane de trois
mesures (2026-11-12, machine de développement 8 cœurs) :

| Niveau | Cible CI `test` (pcov chargé) | Proche d'Apache (pcov désactivé, opcache) |
|--------|------|------|
| Maître (profondeur 4) | 1,31 s | 0,53 s |
| Club (profondeur 3) | 0,18 s | 0,07 s |
| Débutant (profondeur 2) | 0,03 s | 0,01 s |

Avant ces optimisations, le niveau Maître prenait 17,7 à 20 s dans la cible
`test`.

Objectif : Maître en 3 s au plus dans la cible `test`. Le test lent
`testMasterLevelRespondsWithinBudget` échoue au-delà de 5 s, ce qui laisse une
marge aux machines de CI plus lentes. Calcul de la marge en production : la
position de référence la plus lente coûte 1,5 fois Kiwipete, la production n'a
pas pcov (÷ 2,4) et Apache a opcache (÷ 1,2), un serveur ancien ou partagé peut
être 3 fois plus lent : 1,31 s × 1,5 ÷ 2,9 × 3 ≈ 2 s, loin des 30 s de
`max_execution_time`.

Le plus gros gain vient d'une borne à la racine. Après le premier coup, chaque
coup racine est cherché avec une borne tirée du meilleur score bruité ; comme
`minimax` est « fail-soft », un coup écarté par la borne n'aurait pas été choisi
avec sa valeur exacte, quel que soit le bruit tiré. Les coups de référence de
`GoldenMovesTest` et les perft profonds vérifient que le coup choisi et les
coups légaux n'ont pas changé.

pcov instrumente chaque opcode dès qu'il est actif, même avec `--no-coverage` :
il multiplie le temps par 2,4 environ et empêche le JIT. Sous WampServer,
opcache, le JIT et Xdebug dépendent du `php.ini` de WampServer, hors du dépôt ;
Xdebug ralentit PHP plusieurs fois et doit rester désactivé sur la machine qui
sert l'application.

---

← [Organisation du code](organisation-du-code.md) · [Sommaire](README.md) · [Infrastructure et contrats d'API](infrastructure.md) →
