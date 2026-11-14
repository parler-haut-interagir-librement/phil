<?php

declare(strict_types=1);

use Phil\TenancyDatabaseBundle\Infrastructure\Credentials\CredentialSeal;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The name of the cryptographic library.
        'words' => ['libsodium'],
    ],
    'infection' => [
        'mutators' => [
            // A failure with no exit code that did not time out cannot be built: a process that is not running has
            // one, a signal gives 128 and more, and a timeout is told apart before. The fallback only tells PHPStan so.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$result->exitCode \\?\\? -1.*'],
            ],
            // See `DecrementInteger`. And a JSON document of credentials is flat, with every value checked to be a
            // scalar: a deeper limit refuses the same values, a line later, with the same message.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$result->exitCode \\?\\? -1.*',
                    '.*json_decode\\(\\$plain, true, 2, .*',
                ],
            ],
            // Wiping the plain text of the credentials from memory leaves nothing a test, or any caller, can read back.
            // The runner sleeps between two looks at its processes: without the sleep, it looks at them in a tight loop
            // and the results are the same, only sooner by microseconds. Its shutdown function only runs when a fatal
            // error ends the process: the integration test MigrationStopTest ends a console that way and checks that no
            // migration outlives it; the unit suite, which Infection replays, cannot.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*sodium_memzero\\(\\$plain\\);.*',
                    '.*usleep\\(self::POLL_MICROSECONDS\\);.*',
                    '.*register_shutdown_function\\(function \\(\\) use \\(&\\$running\\): void \\{.*',
                ],
            ],
            // The grace period ends on the clock, read between two looks 10 ms apart: it never lands on the deadline
            // itself, so waiting until it or just past it cannot be told apart.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*microtime\\(true\\) < \\$deadline.*'],
            ],
            // The same wiping, in a `finally`.
            'UnwrapFinally' => [
                'ignore' => [CredentialSeal::class.'::open'],
            ],
            'Finally_' => [
                'ignore' => [CredentialSeal::class.'::open'],
            ],
            // The sleep of the runner only while a process runs, or only while none does: the same results, later or
            // sooner by microseconds.
            'NotIdentical' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\[\\] !== \\$running\\) \\{.*'],
            ],
            // The arguments of the PHP the finder gives are those of phpdbg alone: the same executable, from the command
            // line, with or without them.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*new PhpExecutableFinder\\(\\)->find\\(false\\);.*'],
            ],
            // The set of tables a table references holds its keys alone: the ordering reads them with `array_diff_key()`
            // and `[] === $targets`, never their values.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$referenced\\[\\$target\\] = true;.*'],
            ],
            // Whether the console and the process components are installed: the development environment has both, so
            // that only one of them missing cannot be told from none.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*!class_exists\\(ConsoleEvents::class\\) \\|\\| !class_exists\\(Process::class\\).*'],
            ],
        ],
    ],
    'fuzz' => [
        // The readers of what nobody controls, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            // The sealed credentials of a dedicated database, as a row of the application's table holds them.
            'sealed-credentials' => 'tests/Fuzz/sealed-credentials.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // Le déplacement et les magasins ouvrent leurs connexions avec les paramètres de la connexion gérée, que seule
            // Connection::getParams() rend : DoctrineBundle et DBAL n'ont pas d'autre API publique pour les lire.
            [
                'identifier' => 'method.internal',
                'path' => 'src/Infrastructure/Doctrine/PinnedConnection.php',
                'count' => 1,
            ],
        ],
    ],
];
