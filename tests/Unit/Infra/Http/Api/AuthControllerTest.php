<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Infra\Http\Api\AuthController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Shared\Access;
use LeCercle\Shared\Role;
use LeCercle\Tests\Support\FixedGameStats;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryLoginAttempts;
use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\Tests\Support\PlainPasswordHasher;
use LeCercle\Tests\Support\RecordingAdminJournal;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\AuthService;
use LeCercle\User\PresenceService;
use LeCercle\User\UserAdminService;
use LeCercle\User\UserViews;
use PHPUnit\Framework\TestCase;

/** Contrat HTTP de api/auth.php, services branchés sur des doubles en mémoire. */
final class AuthControllerTest extends TestCase
{
    private const SECRET = 'motdepasse-secret';

    private InMemoryUserRepository $users;
    private FakeSession $session;
    private RecordingAdminJournal $journal;
    private AuthController $controller;
    private string $json = '';

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $this->session = new FakeSession();
        $this->journal = new RecordingAdminJournal();
        $access = new Access($this->session, new UserRepositoryMembers($this->users));
        $clock = new FixedClock(new \DateTimeImmutable('2026-05-06 07:08:09', new \DateTimeZone('UTC')));
        $transactions = new ImmediateTransactionRunner();
        $views = new UserViews(new FixedGameStats());
        $this->controller = new AuthController(
            new AuthService($this->users, new PlainPasswordHasher(), $this->session, $access, $clock, $transactions, $views, new InMemoryLoginAttempts()),
            new UserAdminService($this->users, $access, $this->journal, $transactions, $views, $clock),
            new PresenceService($this->users, $access, $clock),
            '203.0.113.9',
        );
    }

    /** @return array{int, array<string, mixed>} */
    private function call(string $json, ?string $userId = null): array
    {
        if ($userId !== null) {
            $this->session->logIn($userId);
        }
        $response = (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => $this->controller->routes());
        $this->json = $response->json;
        $body = json_decode($response->json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        return [$response->status, $body];
    }

    /** Inscrit un compte (le premier est administrateur) et rend son identifiant ; la session reste ouverte. */
    private function register(string $pseudo): string
    {
        [$status, $body] = $this->call(json_encode(['action' => 'register', 'pseudo' => $pseudo, 'email' => "$pseudo@x.fr", 'password' => self::SECRET], JSON_THROW_ON_ERROR));
        self::assertSame(200, $status, $this->json);
        self::assertIsArray($body['user']);

        return (string) $body['user']['id'];
    }

    private function assertNoSecret(): void
    {
        self::assertStringNotContainsString('plain:', $this->json);
        self::assertStringNotContainsString(self::SECRET, $this->json);
    }

    public function testRegister(): void
    {
        [$status, $body] = $this->call('{"action":"register","pseudo":"alice","email":"Alice@X.fr","password":"' . self::SECRET . '"}');

        self::assertSame(200, $status);
        self::assertTrue($body['ok']);
        self::assertIsArray($body['user']);
        self::assertSame(['id', 'pseudo', 'email', 'provider', 'role', 'banned', 'stats', 'createdAt'], array_keys($body['user']));
        self::assertSame('alice', $body['user']['pseudo']);
        self::assertSame('alice@x.fr', $body['user']['email']);
        self::assertSame($body['user']['id'], $this->session->id());
        $this->assertNoSecret();
    }

    public function testFirstRegisteredAccountIsAdminEvenAfterAGuest(): void
    {
        $this->call('{"action":"guest"}');
        $this->callAnonymous('{"action":"me"}');

        [$status, $body] = $this->call('{"action":"register","pseudo":"alice","email":"alice@x.fr","password":"secret1"}');

        self::assertSame(200, $status, $this->json);
        self::assertIsArray($body['user']);
        self::assertSame('admin', $body['user']['role']);
    }

    public function testRegisterConflict(): void
    {
        $this->register('alice');

        self::assertSame([409, ['ok' => false, 'error' => 'Ce pseudo est déjà pris.']], $this->call('{"action":"register","pseudo":"ALICE","email":"b@x.fr","password":"secret1"}'));
    }

    public function testRegisterRequiresEveryField(): void
    {
        self::assertSame([422, ['ok' => false, 'error' => 'Paramètre « email » manquant ou invalide.']], $this->call('{"action":"register","pseudo":"alice","password":"secret1"}'));
    }

    public function testLogin(): void
    {
        $id = $this->register('alice');
        $this->call('{"action":"logout"}');
        self::assertNull($this->session->id());

        [$status, $body] = $this->call('{"action":"login","identifiant":"ALICE@x.fr","password":"' . self::SECRET . '"}');

        self::assertSame(200, $status);
        self::assertIsArray($body['user']);
        self::assertSame($id, $body['user']['id']);
        self::assertSame($id, $this->session->id());
        $this->assertNoSecret();
    }

    public function testLoginWrongPassword(): void
    {
        $this->register('alice');
        $this->call('{"action":"logout"}');

        [$status] = $this->call('{"action":"login","identifiant":"alice","password":"mauvais-mot"}');

        self::assertSame(403, $status);
        self::assertSame('{"ok":false,"error":"Identifiant ou mot de passe incorrect."}', $this->json);
        self::assertNull($this->session->id());
    }

    public function testLoginCountsFailuresPerClientAddress(): void
    {
        for ($i = 0; $i < 20; $i++) {
            self::assertSame(403, $this->call('{"action":"login","identifiant":"inconnu' . $i . '","password":"x"}')[0]);
        }

        self::assertSame(
            [429, ['ok' => false, 'error' => 'Trop de tentatives de connexion. Réessaie dans quelques minutes.']],
            $this->call('{"action":"login","identifiant":"autre","password":"x"}'),
        );
    }

    public function testLoginPasswordMustBeAString(): void
    {
        self::assertSame([422, ['ok' => false, 'error' => 'Paramètre « password » manquant ou invalide.']], $this->call('{"action":"login","identifiant":"alice","password":123}'));
    }

    public function testLoginEmptyFields(): void
    {
        self::assertSame([422, ['ok' => false, 'error' => 'Renseigne ton identifiant et ton mot de passe.']], $this->call('{"action":"login","identifiant":"","password":""}'));
    }

    public function testMeAnonymous(): void
    {
        [$status] = $this->call('{"action":"me"}');

        self::assertSame(200, $status);
        self::assertSame('{"ok":true,"user":null}', $this->json);
    }

    public function testMeLoggedIn(): void
    {
        $id = $this->register('alice');

        [$status, $body] = $this->call('{"action":"me"}');

        self::assertSame(200, $status);
        self::assertIsArray($body['user']);
        self::assertSame($id, $body['user']['id']);
    }

    public function testLogout(): void
    {
        $this->register('alice');

        self::assertSame([200, ['ok' => true]], $this->call('{"action":"logout"}'));
        self::assertNull($this->session->id());
    }

    public function testGuest(): void
    {
        [$status, $body] = $this->call('{"action":"guest"}');

        self::assertSame(200, $status);
        self::assertIsArray($body['user']);
        self::assertSame('guest', $body['user']['provider']);
        self::assertSame($body['user']['id'], $this->session->id());
    }

    public function testGuestWithPseudo(): void
    {
        [$status, $body] = $this->call('{"action":"guest","pseudo":"Visiteur"}');

        self::assertSame(200, $status);
        self::assertIsArray($body['user']);
        self::assertSame('Visiteur', $body['user']['pseudo']);
        self::assertSame([409, ['ok' => false, 'error' => 'Tu es déjà connecté.']], $this->call('{"action":"guest"}'));
    }

    public function testGuestPseudoMustBeAString(): void
    {
        self::assertSame([422, ['ok' => false, 'error' => 'Paramètre « pseudo » manquant ou invalide.']], $this->call('{"action":"guest","pseudo":5}'));
    }

    public function testClaimAdmin(): void
    {
        $this->register('alice');
        $bob = $this->register('bob');

        self::assertSame([409, ['ok' => false, 'error' => 'Un administrateur existe déjà.']], $this->call('{"action":"claimAdmin"}', $bob));
        self::assertSame([403, ['ok' => false, 'error' => 'Connexion requise.']], $this->callAnonymous('{"action":"claimAdmin"}'));
        $this->call('{"action":"guest"}');
        self::assertSame([403, ['ok' => false, 'error' => 'Réservé aux comptes inscrits.']], $this->call('{"action":"claimAdmin"}'));
    }

    public function testClaimAdminWhenNoAdminRemains(): void
    {
        // L'API refuse de rétrograder le dernier administrateur : l'état « aucun admin » est préparé dans le dépôt.
        $alice = $this->register('alice');
        $this->users->save($this->users->find($alice)?->withRole(Role::User) ?? self::fail('compte absent'));

        [$status, $body] = $this->call('{"action":"claimAdmin"}', $alice);

        self::assertSame(200, $status, $this->json);
        self::assertIsArray($body['user']);
        self::assertSame('admin', $body['user']['role']);
    }

    public function testList(): void
    {
        $alice = $this->register('alice');
        $bob = $this->register('bob');

        self::assertSame([403, ['ok' => false, 'error' => 'Action réservée aux administrateurs.']], $this->call('{"action":"list"}', $bob));

        [$status, $body] = $this->call('{"action":"list"}', $alice);
        self::assertSame(200, $status);
        self::assertIsArray($body['users']);
        self::assertSame(['alice', 'bob'], array_column($body['users'], 'pseudo'));
        self::assertSame(['alice@x.fr', 'bob@x.fr'], array_column($body['users'], 'email'));
        $this->assertNoSecret();
    }

    public function testAdminUpdate(): void
    {
        $alice = $this->register('alice');
        $bob = $this->register('bob');

        self::assertSame([200, ['ok' => true]], $this->call('{"action":"adminUpdate","targetId":"' . $bob . '","op":"ban"}', $alice));
        self::assertTrue($this->users->find($bob)?->suspended);
        self::assertSame([[$alice, 'Suspension de bob']], array_map(static fn(array $e): array => array_values($e), $this->journal->entries));
    }

    public function testAdminUpdateErrors(): void
    {
        $alice = $this->register('alice');
        $bob = $this->register('bob');

        self::assertSame([422, ['ok' => false, 'error' => 'Opération inconnue.']], $this->call('{"action":"adminUpdate","targetId":"' . $bob . '","op":"nuke"}', $alice));
        self::assertSame([404, ['ok' => false, 'error' => 'Utilisateur introuvable.']], $this->call('{"action":"adminUpdate","targetId":"u-inconnu","op":"ban"}', $alice));
        self::assertSame([409, ['ok' => false, 'error' => 'Impossible de se suspendre soi-même.']], $this->call('{"action":"adminUpdate","targetId":"' . $alice . '","op":"ban"}', $alice));
        self::assertSame([422, ['ok' => false, 'error' => 'Paramètre « op » manquant ou invalide.']], $this->call('{"action":"adminUpdate","targetId":"' . $bob . '"}', $alice));
        self::assertSame([403, ['ok' => false, 'error' => 'Action réservée aux administrateurs.']], $this->call('{"action":"adminUpdate","targetId":"' . $alice . '","op":"ban"}', $bob));
    }

    public function testPurgeGuests(): void
    {
        $alice = $this->register('alice');
        $bob = $this->register('bob');

        self::assertSame([200, ['ok' => true, 'deleted' => 0]], $this->call('{"action":"purgeGuests"}', $alice));
        self::assertSame([403, ['ok' => false, 'error' => 'Action réservée aux administrateurs.']], $this->call('{"action":"purgeGuests"}', $bob));
    }

    public function testHeartbeatAndOnline(): void
    {
        [$status] = $this->call('{"action":"online"}');
        self::assertSame(200, $status);
        self::assertSame('{"ok":true,"players":[]}', $this->json);
        self::assertSame([403, ['ok' => false, 'error' => 'Connexion requise.']], $this->callAnonymous('{"action":"heartbeat"}'));

        $bob = $this->register('bob');
        self::assertSame([200, ['ok' => true]], $this->call('{"action":"heartbeat"}', $bob));

        self::assertSame([200, ['ok' => true, 'players' => [['id' => $bob, 'pseudo' => 'bob']]]], $this->callAnonymous('{"action":"online"}'));
    }

    public function testRemovedActions(): void
    {
        $alice = $this->register('alice');

        self::assertSame([400, ['ok' => false, 'error' => 'Action inconnue.']], $this->call('{"action":"ssoLogin","provider":"google","email":"a@b.fr","pseudo":"ab"}'));
        self::assertSame([400, ['ok' => false, 'error' => 'Action inconnue.']], $this->call('{"action":"recordResult","gameId":"g1","outcome":"w"}', $alice));
    }

    /** @return array{int, array<string, mixed>} */
    private function callAnonymous(string $json): array
    {
        $this->session->logOut();

        return $this->call($json);
    }
}
