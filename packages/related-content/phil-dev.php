<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The sets of identifiers are read with `isset()` and `array_keys()`, which a `false` value does not change: only
            // the keys count.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->taken = \\[\\$item->id => true\\];.*',
                    '.*\\$this->taken\\[\\$id\\] = true;.*',
                    '.*\\$this->pointing\\[\\$id\\]\\[\\$set->itemId\\] = true;.*',
                ],
            ],
            // Without a broader factor the broader terms weigh nothing: the general formula returns the same score as
            // the shortcut, which only spares reading them.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$this->weight \\* \\$shared;.*'],
            ],
        ],
    ],
    'deptrac' => [
        // The engine sees only Symfony's clock and service contracts; the listener of Bridge\ sees the events of
        // phil/publishing, which stays optional.
        'layers' => [
            'PhilRelatedContent' => '^Phil\\\\RelatedContent\\\\(?!Bridge\\\\).*',
            'PhilRelatedContentBridge' => '^Phil\\\\RelatedContent\\\\Bridge\\\\.*',
        ],
        'ruleset' => [
            'PhilRelatedContent' => ['DefaultAllowed'],
            'PhilRelatedContentBridge' => ['PhilRelatedContent', 'PhilPublishing', 'DefaultAllowed'],
        ],
    ],
];
