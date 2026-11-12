<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The reader of stored disclosures, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'stored-disclosure' => 'tests/Fuzz/stored-disclosure.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['IPTC']],
    'infection' => [
        'mutators' => [
            // `DigitalSourceType::declarable()`: the retired codes are the last cases of the enum, so filtering them out
            // keeps the keys 0 to 16 and `array_values()` returns the same list; it guards a retired code declared
            // later among the others.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_values\\(array_filter\\(self::cases\\(\\), static fn \\(self \\$type\\): bool => !\\$type->isRetired\\(\\)\\)\\);',
                ],
            ],
            // `GenerativeAi::rank()` is only compared with another rank: moving the lowest one down or the highest one up
            // keeps the same order.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['self::None => 0,'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['self::Full => 4,'],
            ],
        ],
    ],
];
