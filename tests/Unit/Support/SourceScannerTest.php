<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\SourceScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceScannerTest extends TestCase
{
    #[DataProvider('cleanSources')]
    public function testCleanSourcesHaveNoForbiddenUse(string $php): void
    {
        self::assertSame([], SourceScanner::forbiddenUses($php));
    }

    /** @return iterable<string, array{string}> */
    public static function cleanSources(): iterable
    {
        yield 'docblock mentioning PDO' => ["<?php\n/** Implémentée en PDO dans Infra, voir time() et \$_SESSION. */\ninterface X {}\n"];
        yield 'line comment' => ["<?php\n// date('Y') serait interdit ici\n\$a = 1;\n"];
        yield 'hash comment' => ["<?php\n# header('X: y')\n\$a = 1;\n"];
        yield 'method named date' => ["<?php\n\$x->date('Y');\n"];
        yield 'static method named header' => ["<?php\nFoo::header('a');\n"];
        yield 'function declaration' => ["<?php\nfunction rand(): int { return 4; }\n"];
        yield 'variable call' => ["<?php\n\$time();\n"];
        yield 'explicit date' => ["<?php\nnew \\DateTimeImmutable('2026-11-12 10:00:00');\n"];
        yield 'identifier containing Pdo' => ["<?php\nnew PdoMembers(\$c);\n"];
    }

    #[DataProvider('dirtySources')]
    public function testForbiddenUsesAreFound(string $php, string $expected): void
    {
        self::assertContains($expected, SourceScanner::forbiddenUses($php));
    }

    /** @return iterable<string, array{string, string}> */
    public static function dirtySources(): iterable
    {
        yield 'date' => ["<?php\n\$y = date('Y');\n", 'date('];
        yield 'fully qualified' => ["<?php\n\$y = \\date('Y');\n", 'date('];
        yield 'gmdate' => ["<?php\ngmdate('c');\n", 'gmdate('];
        yield 'rand' => ["<?php\nrand(1, 6);\n", 'rand('];
        yield 'mt_rand' => ["<?php\nmt_rand();\n", 'mt_rand('];
        yield 'random_bytes' => ["<?php\nrandom_bytes(6);\n", 'random_bytes('];
        yield 'random_int' => ["<?php\nrandom_int(1, 2);\n", 'random_int('];
        yield 'uniqid' => ["<?php\nuniqid();\n", 'uniqid('];
        yield 'hrtime' => ["<?php\nhrtime(true);\n", 'hrtime('];
        yield 'time' => ["<?php\ntime();\n", 'time('];
        yield 'header' => ["<?php\nheader('X: 1');\n", 'header('];
        yield 'setcookie' => ["<?php\nsetcookie('a', 'b');\n", 'setcookie('];
        yield 'cookie' => ["<?php\n\$c = \$_COOKIE['a'];\n", '$_COOKIE'];
        yield 'session' => ["<?php\n\$_SESSION['a'] = 1;\n", '$_SESSION'];
        yield 'server' => ["<?php\n\$m = \$_SERVER['REQUEST_METHOD'];\n", '$_SERVER'];
        yield 'session function' => ["<?php\nsession_start();\n", 'session_start('];
        yield 'pdo' => ["<?php\nfunction f(\\PDO \$p): void {}\n", 'PDO'];
        yield 'shuffle' => ["<?php\nshuffle(\$a);\n", 'shuffle('];
        yield 'str_shuffle' => ["<?php\nstr_shuffle('ab');\n", 'str_shuffle('];
        yield 'array_rand' => ["<?php\narray_rand(\$a);\n", 'array_rand('];
        yield 'lcg_value' => ["<?php\nlcg_value();\n", 'lcg_value('];
        yield 'mt_srand' => ["<?php\nmt_srand(1);\n", 'mt_srand('];
        yield 'srand' => ["<?php\nsrand(1);\n", 'srand('];
        yield 'date_create' => ["<?php\ndate_create('2026-01-01');\n", 'date_create('];
        yield 'date_create_immutable' => ["<?php\ndate_create_immutable();\n", 'date_create_immutable('];
        yield 'PDOStatement' => ["<?php\nfunction f(\\PDOStatement \$s): void {}\n", 'PDOStatement'];
        yield 'PDOException' => ["<?php\ntry {} catch (\\PDOException \$e) {}\n", 'PDOException'];
        yield 'call glued to an inline comment' => ["<?php\nreturn/**/time();\n", 'time('];
        yield 'now' => ["<?php\nnew \\DateTimeImmutable('now');\n", "new \\DateTimeImmutable('now'"];
        yield 'empty constructor' => ["<?php\nnew DateTime();\n", 'new DateTime()'];
        yield 'code after a comment on the same line' => ["<?php\n/* x */ \$y = date('Y');\n", 'date('];
    }

    public function testStripCommentsKeepsLineNumbers(): void
    {
        $php = "<?php\n/**\n * a\n */\n\$x = 1;\n";
        self::assertSame(substr_count($php, "\n"), substr_count(SourceScanner::stripComments($php), "\n"));
        self::assertStringContainsString('$x = 1;', SourceScanner::stripComments($php));
    }

    public function testReferencedModules(): void
    {
        $php = "<?php\nnamespace LeCercle\\Game;\nuse LeCercle\\Shared\\Clock;\nuse LeCercle\\Chess\\Position;\n"
            . "/** @see \\LeCercle\\Infra\\Foo */\nfinal class G { public function f(): \\LeCercle\\Game\\G { return \\LeCercle\\Shared\\Millis::of(1); } }\n";
        self::assertSame(['Chess', 'Game', 'Shared'], SourceScanner::referencedModules($php));
    }
}
