<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()                                                   // the PHP version in composer.json
    ->withComposerBased(laravel: true)                                // Laravel rules matching the installed version
    ->withPreparedSets(deadCode: true, typeDeclarations: true);
