<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit;

use LeCercle\Tests\Support\SourceScanner;
use PHPUnit\Framework\TestCase;

/** Garde d'architecture : lit src/ sans autochargement. */
final class ArchitectureTest extends TestCase
{
    /** @return list<string> chemins relatifs à src/ */
    private function sourceFiles(): array
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f instanceof \SplFileInfo && $f->isFile() && $f->getExtension() === 'php') {
                $files[] = substr($f->getPathname(), \strlen($root) + 1);
            }
        }
        sort($files);

        return $files;
    }

    private function read(string $relative): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2) . '/src/' . $relative);
    }

    /** @return array<string, list<string>> dossier de premier niveau de src/ => fichiers */
    private function filesByModule(): array
    {
        $byModule = [];
        foreach ($this->sourceFiles() as $file) {
            $byModule[explode('/', $file)[0]][] = $file;
        }

        return $byModule;
    }

    /** @param list<string> $allowed */
    private function assertModuleReferencesOnly(string $module, array $allowed): void
    {
        foreach ($this->filesByModule()[$module] ?? [] as $file) {
            $extra = array_diff(SourceScanner::referencedModules($this->read($file)), $allowed);
            self::assertSame([], array_values($extra), "$file dépend de : " . implode(', ', $extra));
        }
    }

    public function testSharedImportsNothingOutsideShared(): void
    {
        $this->addToAssertionCount(1); // garde valable même sans fichier à contrôler
        $this->assertModuleReferencesOnly('Shared', ['Shared']);
    }

    public function testChessDependsOnNothing(): void
    {
        $this->addToAssertionCount(1);
        $this->assertModuleReferencesOnly('Chess', ['Chess']);
    }

    public function testServicesDependOnlyOnChessSharedAndThemselves(): void
    {
        $this->addToAssertionCount(1);
        foreach (array_keys($this->filesByModule()) as $module) {
            if (\in_array($module, ['Chess', 'Shared', 'Infra'], true)) {
                continue;
            }
            $this->assertModuleReferencesOnly($module, ['Chess', 'Shared', $module]);
        }
    }

    public function testOnlyInfraTouchesIo(): void
    {
        foreach ($this->sourceFiles() as $file) {
            if (str_starts_with($file, 'Infra/') || $file === 'Chess/Ai/SystemRandom.php') {
                continue;
            }
            self::assertSame([], SourceScanner::forbiddenUses($this->read($file)), $file);
        }
    }
}
