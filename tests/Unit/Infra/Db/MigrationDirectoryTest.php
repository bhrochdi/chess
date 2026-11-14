<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Db;

use InvalidArgumentException;
use LeCercle\Infra\Db\Migration;
use LeCercle\Infra\Db\MigrationDirectory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationDirectoryTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';
    private const TMP = __DIR__ . '/../../../../build/phpunit-tmp/migrations-bad-name';

    public function testLoadsSortedByNumber(): void
    {
        $names = array_map(
            static fn(Migration $m): string => $m->name,
            MigrationDirectory::load(self::FIXTURES . '/migrations-valid'),
        );

        self::assertSame(['001-a', '002-b'], $names);
    }

    public function testSplitsStatementsOnLineEndingSemicolon(): void
    {
        $statements = MigrationDirectory::load(self::FIXTURES . '/migrations-valid')[0]->statements;

        self::assertCount(2, $statements);
        self::assertStringContainsString("'a;b'", $statements[0]);
        self::assertStringContainsString('CREATE TABLE t2', $statements[1]);
    }

    public function testCommentOnlyPieceIsDropped(): void
    {
        $statements = MigrationDirectory::load(self::FIXTURES . '/migrations-valid')[1]->statements;

        self::assertCount(1, $statements);
        self::assertStringContainsString('CREATE TABLE t3', $statements[0]);
    }

    public function testDuplicateNumberThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MigrationDirectory::load(self::FIXTURES . '/migrations-duplicate');
    }

    /** @return iterable<string, array{string}> */
    public static function badNames(): iterable
    {
        yield 'numéro court' => ['1-a.sql'];
        yield 'majuscules' => ['ABC-x.sql'];
        yield 'souligné' => ['001_a.sql'];
        yield 'nom en majuscules' => ['001-A.sql'];
        yield 'sans nom' => ['001-.sql'];
    }

    #[DataProvider('badNames')]
    public function testBadNameThrows(string $fileName): void
    {
        if (!is_dir(self::TMP)) {
            mkdir(self::TMP, 0o777, true);
        }
        array_map(unlink(...), glob(self::TMP . '/*') ?: []);
        file_put_contents(self::TMP . '/' . $fileName, "CREATE TABLE a (id INT);\n");

        $this->expectException(InvalidArgumentException::class);
        MigrationDirectory::load(self::TMP);
    }

    public function testMissingDirectoryThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        MigrationDirectory::load(self::FIXTURES . '/absent');
    }
}
