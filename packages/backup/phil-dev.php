<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/Restore/RestoreGuardTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Database/CommandRunner.php',
                    'src/Database/DatabaseClient.php',
                ],
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.paramTypeCoverage',
                'paths' => [
                    'src/Encryption/BackupCipher.php',
                    'src/Media/TarReader.php',
                    'src/Media/TarWriter.php',
                ],
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.returnTypeCoverage',
                'path' => 'src/Encryption/BackupCipher.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // libsodium gives the stream's state and header as two strings: checking the type of one before reading
            // the other gives the same value. fstat() of a stream just opened always answers with its size: the
            // fallback of the media file's size is for static analysis.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\$state = \\\\is_string\(\$pushed\[0\] \?\? null\) \? \$pushed\[0\] : \'\';',
                    '\$size = fstat\(\$in\)\[\'size\'\] \?\? 0;',
                ],
            ],
            // See `IncrementInteger`. And count_chars() in mode 0 also gives the byte values that do not occur, with a
            // count of zero: they add nothing to the checksum, so mode 0 and mode 1 give the same sum.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'foreach \(count_chars\(substr_replace\(\$header, \'        \', 148, 8\), 1\) as \$byte => \$count\) \{',
                    '\$header = \\\\is_string\(\$pushed\[1\] \?\? null\) \? \$pushed\[1\] : \'\';',
                    '\$size = fstat\(\$in\)\[\'size\'\] \?\? 0;',
                ],
            ],
            // Four bytes read as hexadecimal never exceed PHP_INT_MAX: hexdec() gives an integer, and the cast only
            // tells its type to static analysis.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\$size = \(int\) hexdec\(sodium_bin2hex\(\$this->read\(\$in, 4\)\)\);',
                ],
            ],
            // The stat cache of PHP goes stale only when another process changes the directory: the functions of PHP
            // that change a file clear it themselves, so no test of one process sees the difference. A long-running
            // worker does. A plain file's stream writes through without a buffer, and its descriptor is freed with
            // the writer: a test cannot see the archive's stream left open one moment more.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'clearstatcache\\(true, \\$this->path\\);',
                    'clearstatcache\\(true, \\$directory\\);',
                    'fclose\\(\\$this->stream\\);',
                ],
            ],
            // The directory of a target was checked to exist just before and has just been resolved: `realpath()` gives
            // `false` only if it vanished in between, and `tempnam()` then refuses the empty path all the same.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$real = \\(string\\) realpath\\(\\$directory\\);',
                ],
            ],
            // The stream of the target is closed in a `finally` so that a failing writer does not keep it open one
            // moment more: PHP frees a local resource when the method exits, and the temporary file is removed by the
            // outer `finally` either way.
            'UnwrapFinally' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$writer\\(\\$out, \\$target\\);',
                ],
            ],
            // A chunk length past the largest chunk is refused so that nothing that large is read into memory; read,
            // it would fail to authenticate all the same, so what stands for the refusal is not observable.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\$plain = \$size > self::CHUNK .*',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
