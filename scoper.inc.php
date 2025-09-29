<?php

use Symfony\Component\Finder\Finder;

return [
    'prefix' => 'ElectionScraperVdA\\PrefixedVendor',
    'output-dir' => 'build/prefixed',
    'finders' => [
        Finder::create()
            ->files()
            ->ignoreVCS(true)
            ->notName('/\.(zip|phar)$/')
            ->exclude(['bin'])
            ->in(__DIR__ . '/vendor')
    ],
    'exclude-namespaces' => [
        'ElectionScraperVdA\\',
        'Psr\\',
        'Composer\\',
    ],
    'exclude-classes' => [
        'Composer\\Autoload\\ClassLoader',
    ],
    'exclude-functions' => [],
    'exclude-constants' => [],
    'patchers' => [],
];
