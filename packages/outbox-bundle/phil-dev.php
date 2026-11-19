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
            // A limit of 0 reaches `LIMIT 0`, which reads nothing, as the early return does: the guard only spares the
            // database a query, which a test of the results cannot tell. A default that the code clamps to 0 right
            // after (the offsets), a default that no caller leaves out (the signal handler's, which Console always
            // fills), the keys of the placeholders that `implode` ignores, and the defaults of a run that mean "none"
            // below 1 (the batches and the time limit) change nothing a caller can see. The size of the batch of
            // `clearErrorMessages()` only sets how many queries the table is read in, and its cursor starts at 0
            // because the positions of an autoincrement column start at 1: a start of -1 reads the same rows.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$limit < 1\\) \\{',
                    '.*int\\|false \\$previousExitCode = 0.*',
                    '.*int \\$offset = 0(,|\\): array).*',
                    '.*array_fill\\(0, 11,.*',
                    '.*public function run\\(.*',
                    '.*function clearErrorMessages\\(int \\$batch = 500\).*',
                    '.*\\$after = 0;.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*int\\|false \\$previousExitCode = 0.*',
                    '.*array_fill\\(0, 11,.*',
                    '.*function clearErrorMessages\\(int \\$batch = 500\).*',
                ],
            ],
            // A numeric string compares with a number as the number it holds does.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\(float\\) \\$value < \\$minimum.*'],
            ],
        ],
    ],
];
