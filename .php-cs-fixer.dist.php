<?php

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())->in([__DIR__ . '/bin', __DIR__ . '/src', __DIR__ . '/tests'])
    ->append(array_filter(
        glob(__DIR__ . '/api/*.php') ?: [],
        static fn(string $file): bool => preg_match('/\Aconfig.*\.php\z/', basename($file)) !== 1,
    ));

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules(['@PER-CS2.0' => true])
    ->setFinder($finder)
    ->setCacheFile(__DIR__ . '/build/php-cs-fixer.cache');
