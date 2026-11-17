<?php

declare(strict_types=1);

use Phil\LtiBundle\Infrastructure\Dbal\DbalNonces;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // The databases read a number given as text: the cast only serves the signature of the column.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return !is_numeric\\(\\$created\\) \\|\\| \\(int\\) \\$created <=.*',
                    // A number of microseconds as text is added as the number it is.
                    '\\$at = \\$score->timestamp->getTimestamp\\(\\) \\* 1_000_000 \\+ \\(int\\) \\$score->timestamp->format\\(.u.\\);',
                ],
            ],
            // The configuration refuses, before the services are registered, a master key, an issuer and a token address
            // that are not text: the casts only tell the types of the services what the configuration already gave.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(string\\) \\$keys\\[.master_key.\\].*',
                    '\\$issuer = \\(string\\) \\$config\\[.platform.\\]\\[.issuer.\\];',
                    '\\(string\\) \\$config\\[.platform.\\]\\[.token_url.\\],',
                ],
            ],
            // The router leaves out the parameters that are null: the filter only tells the type of the parameters of
            // PlatformUrls::url() what the router does anyway.
            'UnwrapArrayFilter' => ['ignoreSourceCodeByRegex' => ['\\$query = array_filter\\(\\$filters \\+ .*']],
            // The secondary indexes are named after their table and the primary key is not: on SQLite, where mutation
            // testing runs, the primary key is read back under the name the definition gives it, so the whole prefix and
            // the table name alone keep it out alike; on MySQL and PostgreSQL, where the integration suite runs the
            // command, the prefix is what keeps it out.
            'ConcatOperandRemoval' => ['ignoreSourceCodeByRegex' => ['.*str_starts_with\\(\\$this->identifier\\(\\$index\\), \\$name\\.\'_\'\\).*']],
            // Without the early return, the insert that follows meets the same row and answers false all the same.
            'ReturnRemoval' => ['ignore' => [DbalNonces::class.'::use::43']],
        ],
    ],
];
