<?php

declare(strict_types=1);

use Phil\ReviewsBundle\Infrastructure\Doctrine\DbalReviewRepository;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
$unreached = [
    '.*\\$totals\\[\'reviews\'\\] \\?\\? 0.*',
    '.*max\\(1, \\$rated\\).*',
    '.*\\$row\\[\'(review_count|rating_sum|foreign_count)\'\\] \\?\\? 0.*',
    '.*\\$row\\[\'suspended\'\\] \\?\\? false.*',
];

return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // A spread of an array with string keys into a list literal takes the values in their order, as array_values
            // does before it: the keys of the criteria are never read.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*foreach \\(\\[\\$content->overall, \\.\\.\\.array_values\\(\\$content->criteria\\)\\] as \\$score\\) \\{.*',
                ],
            ],
            // Both only narrow the type of what the container and the driver give — the names of the bundles are strings,
            // the identifiers of the votes are text — for the static analysis; no value they take is changed by them.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$bundles\\)\\).*',
                    '.*array_map\\(Columns::string\\(\\.\\.\\.\\), \\$this->connection->fetchFirstColumn\\(.*',
                ],
            ],
            // Without rows to read the scores of, the query of the scores returns nothing: the early return only spares it.
            'ReturnRemoval' => [
                'ignore' => [DbalReviewRepository::class.'::hydrate'],
            ],
            // The columns of a primary key are not null whatever their declaration says.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*->addColumn\\(self::column\\(\'(criterion|label|voter|subject_type|subject_id)\', Types::STRING, true, .*',
                    '.*return self::column\\(\\$name, Types::STRING, true, 36\\)->edit\\(\\)->setFixed\\(true\\)->create\\(\\);.*',
                ],
            ],
            // The aggregates always give their columns, and a group has one row at least: what stands after "??" or in
            // "max(1, ...)" is never reached.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => $unreached],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => $unreached],
            'FalseValue' => ['ignoreSourceCodeByRegex' => $unreached],
            // The pages need Twig, the router and the CSRF manager, which every installation the tests can build has
            // together: no test makes one of them absent while the others stay.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(!class_exists\\(Environment::class\\) \\|\\| !interface_exists\\(UrlGeneratorInterface::class\\) \\|\\| !interface_exists\\(CsrfTokenManagerInterface::class\\)\\) \\{.*',
                ],
            ],
        ],
    ],
];
