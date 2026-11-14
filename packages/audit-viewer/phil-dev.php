<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // The number is passed on to `fputcsv()` by `array_map()`, which is not under `strict_types`: the cell takes
            // it as the string it is cast to.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\(string\\) \\$row->sequence,'],
            ],
            // `fputcsv()` answers an integer or `false`, and `false` is no more above 0 than the 0 it is cast to.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*return 0 < \\(int\\) @fputcsv.*'],
            ],
            // A trailing line feed is the one thing the `D` of the shape adds: `createFromFormat()` refuses it as trailing
            // data, and the instant is refused all the same.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\\\d\\{4\\}-.*'],
            ],
            // `Response` adds `private` to the `Cache-Control` it holds when neither `public` nor `private` is there: the
            // call states what the response would say anyway, for the day Symfony does not.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['\\$response->setPrivate\\(\\);'],
            ],
        ],
    ],
    'fuzz' => [
        // The filters of the log on query strings nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'log-filters' => 'tests/Fuzz/log-filters.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // EasyAdmin's ActionsExtensionInterface declares its AdminContext parameters without their entity type, and
            // PHPStan refuses any type given to them in an implementation as not contravariant: the two methods keep the
            // interface's own signature.
            [
                'identifier' => 'missingType.generics',
                'path' => 'src/Infrastructure/EasyAdmin/AuditHistoryActions.php',
                'count' => 2,
            ],
        ],
    ],
];
