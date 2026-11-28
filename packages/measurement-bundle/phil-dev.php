<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The phil_measurement column type is a type of Doctrine DBAL.
        'allow' => ['^Doctrine\\\\DBAL\\\\.*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // A prototype named by useAttributeAsKey() keeps its keys, family names and unit codes, whichever way
            // normalizeKeys() is set: the option guards the dashes of XML element names, which never reach this tree.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*->normalizeKeys\\(false\\).*'],
            ],
        ],
    ],
];
