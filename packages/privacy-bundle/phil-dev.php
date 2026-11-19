<?php

declare(strict_types=1);

use Phil\PrivacyBundle\Application\Export\ZipFile;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The names of the cryptography and the regular expressions, and the terms of Doctrine and of the redaction.
        'words' => ['libsodium', 'HMAC', 'PCRE', 'embeddables', 'retagged', 'unredacted', 'uncompiled'],
    ],
    'fuzz' => [
        // The readers of what nobody controls, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'consent-token' => 'tests/Fuzz/consent-token.php',
            // The address of a third-party frame, as a template passes it to privacy_embed().
            'embed-source' => 'tests/Fuzz/embed-source.php',
            // The cookie inventory, as its YAML file decodes.
            'cookie-inventory' => 'tests/Fuzz/cookie-inventory.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de Doctrine, PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'paths' => [
                    'src/Infrastructure/Database/ConnectionIdentity.php',
                    'tests/Unit/AnonymizationWiringTest.php',
                    'tests/Unit/Application/Anonymization/AnonymizationRunTest.php',
                ],
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'staticMethod.internalClass',
                'path' => 'src/Infrastructure/Database/DbalAnonymizationTarget.php',
                'count' => 1,
            ],
            // à corriger : fixture qui étend une classe concrète.
            [
                'identifier' => 'symplify.forbiddenExtendOfNonAbstractClass',
                'path' => 'tests/Unit/Infrastructure/Doctrine/Fixture/Entity/Partner.php',
                'count' => 1,
            ],
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'path' => 'src/Application/Exception/ErasureIncomplete.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The length of the salt drawn for an anonymisation (`random_bytes(32)`): 31 or 33 random bytes give a salt
            // as unpredictable, never kept, which no test can measure. And the 4 GiB limit of the plain ZIP format in
            // ZipFile::add(): a test would need more than 4 GiB in memory to reach it.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$salt \\?\\?= random_bytes\\(32\\);',
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The salt and the ZIP limit, as for `DecrementInteger`. And the depth of the consent cookie's JSON: its
            // deepest value, a granted name, sits at depth 3; a fourth level only reads arrays nested in that list,
            // which the shape check refuses right after.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$salt \\?\\?= random_bytes\\(32\\);',
                    '\\$data = json_decode\\(sodium_base642bin\\(\\$payload, .*\\), true, 3, \\\\JSON_THROW_ON_ERROR\\);',
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The identifiers of the due requests come from a column of strings: the filter and the reindexing only give
            // PHPStan the list of strings the database already returns.
            'UnwrapArrayFilter' => [
                'ignore' => [DbalAccountDeletions::class.'::dueIdentifiers'],
            ],
            'UnwrapArrayValues' => [
                'ignore' => [DbalAccountDeletions::class.'::dueIdentifiers'],
            ],
            // The optional integrations: the console and Doctrine DBAL are both installed in development, where the
            // suite runs; that a single one is missing only shows installed on its own, which the standalone check of
            // the package does without them in `require`. And the ZIP limit, as for `DecrementInteger`.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(!class_exists\\(Command::class\\) \\|\\| !class_exists\\(Connection::class\\)\\) \\{',
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The ZIP limit, as for `DecrementInteger`.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The date parts of the archive's headers come from DateTimeImmutable::format() as decimal strings, which
            // PHP's comparison, arithmetic and shift operators read as the same integers: the casts only give PHPStan
            // its integers.
            'CastInt' => [
                'ignore' => [ZipFile::class.'::__construct', ZipFile::class.'::dosTime', ZipFile::class.'::dosDate'],
            ],
            // The configuration tree always gives `guest_requests.enabled`, which GuestRequestServices reads without a
            // default: the `?? false` that types it for PHPStan is never reached.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$builder->setParameter\\(self::MAILED_LINKS, true === \\(\\$guest\\[\'enabled\'\\] \\?\\? false\\).*',
                ],
            ],
            // The names of the archive only answer isset(), which holds for false as for true.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->names\\[\\$name\\] = true;',
                ],
            ],
        ],
    ],
];
