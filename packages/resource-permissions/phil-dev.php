<?php

declare(strict_types=1);

use Phil\ResourcePermissions\Model\Model;
use Phil\ResourcePermissions\RelationChecker;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP on phil/resource; the DBAL store is an adapter, which alone sees Doctrine.
        'layers' => [
            'PhilResourcePermissions' => '^Phil\\\\ResourcePermissions\\\\(?!Dbal\\\\).*',
            'PhilResourcePermissionsAdapter' => '^Phil\\\\ResourcePermissions\\\\Dbal\\\\.*',
        ],
        'ruleset' => [
            'PhilResourcePermissions' => ['DefaultAllowed', 'PhilResource'],
            'PhilResourcePermissionsAdapter' => ['PhilResourcePermissions', 'DefaultAllowed', 'PhilResource', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The relation checker on graphs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'relation-checker' => 'tests/Fuzz/relation-checker.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The rows a statement deleted, and an expiry read back: every driver gives an integer already; the casts
            // only tell PHPStan, which types them int|string and mixed.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \\(int\\) \\$rows;.*',
                    '.*is_numeric\\(\\$expires\\) \\? \\(int\\) \\$expires.*',
                ],
            ],
            // A computed rewrite always holds its relation, as only `Rewrite::computed()` builds one; the cast tells
            // PHPStan that `?string` is a string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*RewriteKind::Computed => \\(string\\) \\$this->relation,.*'],
            ],
            // A read asks for one tuple more than the limit to know the limit was passed; asking for two tells the
            // same, and so does a parent read of three where more than one is already a refusal.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*fanOut\\(\\) \\+ 1.*',
                    '.*candidates\\(\\) \\+ 1.*',
                    '.*parentRelation, 2\\);.*',
                ],
            ],
            // Only whether the cuts changed is ever read, not which way they went.
            'Increment' => [
                'ignoreSourceCodeByRegex' => ['.*\\+\\+\\$this->cuts;.*'],
            ],
            // `isset()` is true for a key holding false as well: the value of a marker is never read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->inside\\[\\$step\\] = true;.*',
                    '.*\\$seen\\[\\$node->toString\\(\\)\\] = true;.*',
                ],
            ],
            // A traversal is made for one call and dropped when it throws: the step left or not left is never seen.
            'UnwrapFinally' => [
                'ignore' => [
                    RelationChecker::class.'::evaluate',
                    RelationChecker::class.'::gather',
                ],
            ],
            // A set or an every-subject asked for gets the every-subject of its kind as a candidate too, which the
            // tuples read never reach: `reaches()` is false for a set and the same tuple for an every-subject.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*\\$subject->isSet\\(\\) \\|\\| \\$subject->isEvery\\(\\) \\? \\[\\$subject\\].*'],
            ],
            // A related rewrite has no children, so the fall-through into the default arm walks nothing.
            'ReturnRemoval' => [
                'ignore' => [Model::class.'::validate'],
            ],
            // An entry's relation is `allow.<action>` or `deny.<action>`, so `deny` alone never starts another one.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*str_starts_with\\(\\$tuple->relation, Decision::Deny->value.*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
];
