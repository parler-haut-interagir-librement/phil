<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this monorepo: the globs of its packages, each of which keeps its own paths and
 * exceptions in its own phil-dev.php, and the terms no package names. See vendor/phil/dev-tools/doc/configuration.md
 * for every section.
 */
return [
    // Every class lives in a package; the root keeps only the bootstrap of the test runs.
    'paths' => ['tests'],
    'packages' => ['packages/*'],

    'unused' => [
        // The root holds no code of its own, only the packages it develops: composer-unused counts its `php`
        // requirement as a package nothing uses.
        'ignore' => ['php'],
    ],

    'deptrac' => [
        // What every library and every domain may use: the default of phil/dev-tools — the Symfony components and
        // contracts, the polyfills and the PSR interfaces —, the Doctrine mapping attributes, which the domain models
        // of the bundles carry, the rest of Doctrine staying outside the domain, the URI extension of PHP 8.5, whose
        // WHATWG parser reads an address as a browser does, as phil/url does on it, and `\SortDirection`, an enum of
        // PHP 8.6 that symfony/polyfill-php86 brings to PHP 8.5 and Doctrine Collections orders by.
        'default' => [
            '^SortDirection$',
            '^Uri\\\\.*',
            '^Symfony\\\\Component\\\\.*',
            '^Symfony\\\\Contracts\\\\.*',
            '^Symfony\\\\Polyfill\\\\.*',
            '^Psr\\\\.*',
            '^Doctrine\\\\ORM\\\\Mapping\\\\.*',
        ],
    ],

    'rector' => [
        'skip' => [
            // A test of phil/commerce-bundle names the processor of phil/commerce-promotions-bundle by a string: the
            // bundle does not depend on that module, which the monorepo alone loads next to it, and `::class` would
            // make the test require it.
            StringClassNameToClassConstantRector::class => [
                'packages/commerce-bundle/tests/Unit/PhilCommerceBundleLifeTest.php',
            ],
        ],
    ],

    'docs' => [
        // The French mirrors catch up with the English documentation: their findings are reported, not blocking.
        'translations' => 'advisory',
    ],
];
