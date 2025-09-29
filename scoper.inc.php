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
            ->exclude(['composer', 'bin'])
            ->in(__DIR__ . '/vendor')
    ],
    'exclude-namespaces' => [
        'ElectionScraperVdA\\',
        'Psr\\',
    ],
    'exclude-classes' => [
        'Composer\\Autoload\\ClassLoader',
    ],
    'exclude-functions' => [
        'GuzzleHttp\\Psr7\\str',
        'GuzzleHttp\\Psr7\\uri_for',
    ],
    'exclude-constants' => [],
    'patchers' => [],
];
