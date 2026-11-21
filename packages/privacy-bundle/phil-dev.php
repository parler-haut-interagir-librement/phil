<?php

declare(strict_types=1);

use Phil\PrivacyBundle\Application\Export\ZipFile;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalErasureRequests;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The names of the cryptography and the regular expressions, the terms of Doctrine and of the redaction, the
        // reason information cannot be described, and the name of the Québec authority.
        'words' => ['libsodium', 'HMAC', 'PCRE', 'embeddables', 'retagged', 'unredacted', 'uncompiled', 'undescribed', "d'accès"],
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
            // A store named by a number reaches the effects as an integer key, whatever the type says: the test of the
            // JSON object of the effects gives one on purpose.
            [
                'identifier' => 'argument.type',
                'path' => 'tests/Unit/Infrastructure/Database/DbalErasureRequestsTest.php',
                'count' => 2,
            ],
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
            // The length of the salt drawn for an anonymisation (`random_bytes(32)`), and of the secret nobody holds that
            // retires a request's link: 31 or 33 random bytes give a value as unpredictable, never kept, which no test
            // can measure. And the 4 GiB limit of the plain ZIP format in
            // ZipFile::add(): a test would need more than 4 GiB in memory to reach it.
            // And the batch of a purge of the register of incidents, and the fallback of a correction's number, which a
            // NOT NULL INTEGER column never reaches.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int PURGE_BATCH = 500;',
                    'is_numeric\\(\\$row\\[\'sequence\'\\]\\) \\? \\(int\\) \\$row\\[\'sequence\'\\] : 0,',
                    '\\$salt \\?\\?= random_bytes\\(32\\);',
                    '.*\'token_hash\' => hash\\(\'sha256\', random_bytes\\(32\\)\\).*',
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The salt, the secret and the ZIP limit, as for `DecrementInteger`. And the depth of the consent cookie's JSON: its
            // deepest value, a granted name, sits at depth 3; a fourth level only reads arrays nested in that list,
            // which the shape check refuses right after.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int PURGE_BATCH = 500;',
                    'is_numeric\\(\\$row\\[\'sequence\'\\]\\) \\? \\(int\\) \\$row\\[\'sequence\'\\] : 0,',
                    '\\$salt \\?\\?= random_bytes\\(32\\);',
                    '.*\'token_hash\' => hash\\(\'sha256\', random_bytes\\(32\\)\\).*',
                    '\\$data = json_decode\\(sodium_base642bin\\(\\$payload, .*\\), true, 3, \\\\JSON_THROW_ON_ERROR\\);',
                    'if \\(\\$offset \\+ 30 \\+ \\\\strlen\\(\\$name\\) \\+ \\\\strlen\\(\\$compressed\\) > self::MAX_BYTES \\|\\| \\\\strlen\\(\\$content\\) > self::MAX_BYTES\\) \\{',
                ],
            ],
            // The reindexing of the steps' modules and the casts of the effects' store names, which only give PHPStan its
            // types: SQLite and PHP's array keys return the same strings and the same keys with or without them.
            'UnwrapArrayMap' => [
                'ignore' => [DbalErasureRequests::class.'::reopen'],
            ],
            'CastString' => [
                'ignore' => [DbalErasureRequests::class.'::readEffects'],
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
            // The number of a correction of the register of incidents: every driver returns the integer of its column.
            'CastInt' => [
                'ignore' => [ZipFile::class.'::__construct', ZipFile::class.'::dosTime', ZipFile::class.'::dosDate', DbalIncidentRegister::class.'::revise', DbalIncidentRegister::class.'::revisions'],
            ],
            // The instants RetentionPeriod::lastsAtLeast() compares from: each duration the tests tell apart is told apart
            // from several of them, so that removing the first or the last leaves the answer as it is.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'private const array REFERENCES = \\[',
                ],
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
