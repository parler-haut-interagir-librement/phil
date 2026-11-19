<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A batch entry always carries its batch: the null-safe access only serves the type of `Entry::$batch`.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$entry->batch\\?->title.*'],
            ],
            // `format('N')` is a numeric string, which the arithmetic reads as the integer the cast gives.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$back = \\(\\(int\\) \\$local->format\\(\'N\'\\).*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['iCalendar']],
];
