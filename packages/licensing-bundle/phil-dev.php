<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The names of the licenses and of their parts, as their texts write them.
        'words' => ['ORC', 'OGL', 'SPDX', 'ShareAlike'],
    ],
    'infection' => [
        'mutators' => [
            // The forms need symfony/form and symfony/validator, both installed with the bundle in development: which of
            // the two missing skips them cannot be told apart.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['if \\(!class_exists\\(AbstractType::class\\) \\|\\| !class_exists\\(Constraint::class\\)\\) \\{'],
            ],
        ],
    ],
];
