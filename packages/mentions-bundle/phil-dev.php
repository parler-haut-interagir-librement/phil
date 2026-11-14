<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The test of the prepended configuration registers the extensions of the bundles it configures, as the
            // kernel does: DoctrineBundle marks its extension internal.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilMentionsBundleTest.php',
                'count' => 1,
            ],
        ],
    ],
    'rector' => [
        // The class of the recipe lives in the application once copied: the test names it by a string, since no autoloader
        // of the package knows it.
        'skip' => [
            StringClassNameToClassConstantRector::class => ['tests/Unit/RecipeTest.php'],
        ],
    ],
    'fuzz' => [
        // The extraction and the linking on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'text-mentions' => 'tests/Fuzz/text-mentions.php',
        ],
    ],
];
