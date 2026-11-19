<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The default limits of `LiblouisTranslator` are a policy, documented, not a behaviour; the behaviour of each
            // limit is tested with limits a test can reach. `stop(0)` is the explicit form of what the destructor of the
            // process does as the exception leaves the method: nothing a test sees tells it apart from its mutants.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*int \\$max(Input|Output)Bytes = .*', '.*\\$process->stop\\(0\\);']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*int \\$max(Input|Output)Bytes = .*', '.*\\$process->stop\\(0\\);']],
            'MethodCallRemoval' => ['ignoreSourceCodeByRegex' => ['.*\\$process->stop\\(0\\);']],
            // A finished process has an exit code: the cast only tells PHPStan so.
            'CastInt' => ['ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$process->getExitCode\\(\\);']],
        ],
    ],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        'words' => ['liblouis', 'LOUIS_TABLEPATH', 'lou_translate', 'utb', 'ctb', 'cti', 'UEB', 'BRF', 'français', 'uniformisé', 'abrégé'],
    ],
];
