<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // Run before the field's own transformer, the drawing would decode the submitted JSON itself, and the field
            // would check the same document after it: the model is the same either way.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['\$builder->addModelTransformer\(new StoredMarkupTransformer\(.*']],
        ],
    ],
];
