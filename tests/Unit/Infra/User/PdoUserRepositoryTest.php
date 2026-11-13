<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\User;

use LeCercle\Infra\User\PdoUserRepository;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\Role;
use LeCercle\User\Provider;
use LeCercle\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Classement des erreurs d'insertion, sur un PDO simulé (les cas réels sont en intégration). */
final class PdoUserRepositoryTest extends TestCase
{
    private function repoFailingWith(int $driverCode, string $driverMessage): PdoUserRepository
    {
        $error = new \PDOException('SQLSTATE[HY000]: ' . $driverCode . ' ' . $driverMessage);
        $error->errorInfo = ['HY000', $driverCode, $driverMessage];
        $stmt = $this->createStub(\PDOStatement::class);
        $stmt->method('execute')->willThrowException($error);
        $pdo = $this->createStub(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return new PdoUserRepository($pdo);
    }

    private function user(): User
    {
        return new User('u000000000001', 'Alice', 'alice@x.fr', Provider::Local, Role::User, 'h', false, new \DateTimeImmutable('2026-06-01', new \DateTimeZone('UTC')));
    }

    /** @return iterable<string, array{string, string}> */
    public static function duplicates(): iterable
    {
        yield 'pseudo (MySQL 8)' => ["Duplicate entry 'alice' for key 'users.uniq_pseudo'", 'Ce pseudo est déjà pris.'];
        yield 'e-mail (MySQL 8)' => ["Duplicate entry 'a@x.fr' for key 'users.uniq_email'", 'Un compte existe déjà avec cet e-mail.'];
        yield 'e-mail dont la valeur contient uniq_pseudo' => ["Duplicate entry 'uniq_pseudo@x.fr' for key 'users.uniq_email'", 'Un compte existe déjà avec cet e-mail.'];
        yield 'pseudo dont la valeur contient uniq_email' => ["Duplicate entry 'uniq_email' for key 'users.uniq_pseudo'", 'Ce pseudo est déjà pris.'];
    }

    #[DataProvider('duplicates')]
    public function testDuplicateIsClassifiedByKeyName(string $driverMessage, string $expected): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage($expected);
        $this->repoFailingWith(1062, $driverMessage)->add($this->user());
    }

    public function testDuplicateOnAnotherKeyIsNotMasked(): void
    {
        $this->expectException(\PDOException::class);
        $this->repoFailingWith(1062, "Duplicate entry 'uniq_pseudo' for key 'users.PRIMARY'")->add($this->user());
    }

    public function testDeadlockDuringAddIsAConflict(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Inscription simultanée, réessaie.');
        $this->repoFailingWith(1213, 'Deadlock found when trying to get lock; try restarting transaction')->add($this->user());
    }

    public function testOtherErrorsAreNotMasked(): void
    {
        $this->expectException(\PDOException::class);
        $this->repoFailingWith(1205, 'Lock wait timeout exceeded; try restarting transaction')->add($this->user());
    }
}
