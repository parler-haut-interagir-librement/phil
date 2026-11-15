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
                    '\\$row = .*\'scored_at\' => \\(int\\) \\$score->timestamp.*',
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
            // Without the early return, the insert that follows meets the same row and answers false all the same.
            'ReturnRemoval' => ['ignore' => [DbalNonces::class.'::use::43']],
        ],
    ],
];
