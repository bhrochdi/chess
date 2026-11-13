<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\Member;
use LeCercle\Shared\Role;
use LeCercle\Shared\TimeControl;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentFormat;
use LeCercle\Tournament\TournamentStatus;
use LeCercle\Tournament\TournamentSummary;
use LeCercle\Tournament\TournamentViews;
use PHPUnit\Framework\TestCase;

final class TournamentViewsTest extends TestCase
{
    private const KEYS = [
        'id', 'name', 'description', 'format', 'tc', 'status', 'registrationOpen', 'plannedRounds',
        'currentRound', 'canAdvance', 'players', 'rounds', 'standings', 'registered', 'myPairing',
        'createdBy', 'createdAt', 'startedAt', 'finishedAt',
    ];

    private \DateTimeImmutable $t0;
    private TournamentViews $views;

    /** @var array<string, Member> */
    private array $members;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00', new \DateTimeZone('UTC'));
        $this->views = new TournamentViews();
        $this->members = [];
        foreach (['A' => 'alice', 'B' => 'bob', 'C' => 'carol', 'D' => 'dave', 'Z' => 'zoe'] as $id => $pseudo) {
            $this->members[$id] = new Member($id, $pseudo, Role::User, false, false);
        }
        $this->members['R'] = new Member('R', 'root', Role::Admin, false, false);
    }

    /** @param list<string> $players */
    private function tournament(array $players, TournamentFormat $format = TournamentFormat::RoundRobin, bool $start = true): Tournament
    {
        $t = Tournament::create('t000000000001', 'Open', 'Cadence rapide', $format, new TimeControl(600, 0), null, 'R', $this->t0);
        foreach ($players as $id) {
            $t->register($id);
        }
        if ($start) {
            $t->start(['A' => 'alice', 'B' => 'bob', 'C' => 'carol', 'D' => 'dave', 'Z' => 'zoe'], $this->t0->modify('+1 hour'));
        }

        return $t;
    }

    public function testTournamentHasTheKeysOfTheContract(): void
    {
        $view = $this->views->tournament($this->tournament(['A', 'B']), $this->members['A'], $this->members);

        self::assertSame(self::KEYS, array_keys($view));
        self::assertSame('t000000000001', $view['id']);
        self::assertSame('Open', $view['name']);
        self::assertSame('Cadence rapide', $view['description']);
        self::assertSame('round-robin', $view['format']);
        self::assertSame(['base' => 600, 'inc' => 0], $view['tc']);
        self::assertSame('en-cours', $view['status']);
        self::assertFalse($view['registrationOpen']);
        self::assertNull($view['plannedRounds']);
        self::assertSame(1, $view['currentRound']);
        self::assertFalse($view['canAdvance'], 'toutes rondes à 2 : une seule ronde');
        self::assertSame([['id' => 'A', 'pseudo' => 'alice'], ['id' => 'B', 'pseudo' => 'bob']], $view['players']);
        self::assertSame(['id' => 'R', 'pseudo' => 'root'], $view['createdBy']);
        self::assertSame(1794477600000, $view['createdAt']);
        self::assertSame(1794481200000, $view['startedAt']);
        self::assertNull($view['finishedAt']);
    }

    public function testPointsAreFloats(): void
    {
        $t = $this->tournament(['A', 'B']);
        $t->setResult(1, 1, Result::Draw);

        $view = $this->views->tournament($t, null, $this->members);

        self::assertSame(
            [
                ['player' => ['id' => 'A', 'pseudo' => 'alice'], 'points' => 0.5, 'played' => 1, 'wins' => 0],
                ['player' => ['id' => 'B', 'pseudo' => 'bob'], 'points' => 0.5, 'played' => 1, 'wins' => 0],
            ],
            $view['standings'],
        );
        self::assertSame('1/2-1/2', $view['rounds'][0]['pairings'][0]['result']);
    }

    public function testStandingsTiebreakUsesPseudosNotIds(): void
    {
        // l'identifiant « @z » se trie avant « A » : seul le pseudo place zoe après alice
        $members = $this->members;
        $members['@z'] = new Member('@z', 'zoe', Role::User, false, false);

        $view = $this->views->tournament($this->tournament(['@z', 'A'], start: false), null, $members);

        self::assertSame(['alice', 'zoe'], array_map(static fn(array $r): string => $r['player']['pseudo'], $view['standings']));
    }

    public function testDeletedPlayerIsShownWithAPlaceholder(): void
    {
        $members = $this->members;
        unset($members['B']);

        $view = $this->views->tournament($this->tournament(['A', 'B']), null, $members);

        self::assertSame(['id' => 'B', 'pseudo' => '(compte supprimé)'], $view['players'][1]);
        self::assertSame(['id' => 'B', 'pseudo' => '(compte supprimé)'], $view['rounds'][0]['pairings'][0]['black']);
        self::assertSame(['id' => 'B', 'pseudo' => '(compte supprimé)'], $view['standings'][1]['player']);
    }

    public function testRoundsAfterTheCurrentOneAreHiddenFromNonAdminsWhileRunning(): void
    {
        $t = $this->tournament(['A', 'B', 'C', 'D']);

        self::assertSame([1], $this->roundNumbers($this->views->tournament($t, $this->members['A'], $this->members)));
        self::assertSame([1], $this->roundNumbers($this->views->tournament($t, null, $this->members)));
        self::assertSame([1, 2, 3], $this->roundNumbers($this->views->tournament($t, $this->members['R'], $this->members)));

        $t->close($this->t0);
        self::assertSame([1, 2, 3], $this->roundNumbers($this->views->tournament($t, null, $this->members)), 'terminé : tout est public');
    }

    public function testViewerRegistrationAndPairing(): void
    {
        $t = $this->tournament(['A', 'B', 'C', 'D']);

        $alice = $this->views->tournament($t, $this->members['A'], $this->members);
        self::assertTrue($alice['registered']);
        self::assertSame(['round' => 1, 'board' => 1, 'gameId' => null], $alice['myPairing']);

        $carol = $this->views->tournament($t, $this->members['C'], $this->members);
        self::assertSame(['round' => 1, 'board' => 2, 'gameId' => null], $carol['myPairing']);

        $zoe = $this->views->tournament($t, $this->members['Z'], $this->members);
        self::assertFalse($zoe['registered']);
        self::assertNull($zoe['myPairing']);

        $t->close($this->t0);
        self::assertNull($this->views->tournament($t, $this->members['A'], $this->members)['myPairing'], 'plus de rencontre une fois clos');

        $pending = $this->views->tournament($this->tournament(['A', 'B'], start: false), $this->members['A'], $this->members);
        self::assertTrue($pending['registered']);
        self::assertNull($pending['myPairing'], 'pas de rencontre avant le lancement');
    }

    public function testByeHasNoBlackPlayer(): void
    {
        $view = $this->views->tournament($this->tournament(['A', 'B', 'C'], TournamentFormat::Elimination), null, $this->members);

        self::assertSame(
            ['board' => 1, 'white' => ['id' => 'A', 'pseudo' => 'alice'], 'black' => null, 'result' => '1-0', 'gameId' => null],
            $view['rounds'][0]['pairings'][0],
        );
    }

    public function testSummariesAreSortedByStatusThenNewestFirst(): void
    {
        $at = fn(string $modify): \DateTimeImmutable => $this->t0->modify($modify);
        $list = [
            new TournamentSummary('t1', 'Ancien', TournamentFormat::RoundRobin, null, TournamentStatus::Finished, 4, $at('+1 hour'), $at('+0 hour')),
            new TournamentSummary('t2', 'Récent', TournamentFormat::Swiss, null, TournamentStatus::Finished, 6, $at('+3 hour'), $at('+2 hour')),
            new TournamentSummary('t3', 'Ouvert', TournamentFormat::Elimination, new TimeControl(180, 2), TournamentStatus::Registration, 1, null, $at('+5 hour')),
            new TournamentSummary('t4', 'En cours', TournamentFormat::RoundRobin, null, TournamentStatus::Running, 3, $at('+4 hour'), $at('+1 hour')),
        ];

        $views = $this->views->summaries($list);

        self::assertSame(['t4', 't3', 't2', 't1'], array_column($views, 'id'));
        self::assertSame(
            ['id' => 't3', 'name' => 'Ouvert', 'format' => 'elimination', 'tc' => ['base' => 180, 'inc' => 2], 'status' => 'inscriptions', 'playerCount' => 1, 'startedAt' => null],
            $views[1],
        );
        self::assertSame(1794492000000, $views[0]['startedAt']);
    }

    /**
     * @param array<string, mixed> $view
     * @return list<int>
     */
    private function roundNumbers(array $view): array
    {
        return array_column($view['rounds'], 'number');
    }
}
