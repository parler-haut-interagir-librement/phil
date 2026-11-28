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
            // The ORM only asks whether the refresh hint is set, never what it holds (UnitOfWork::createEntity() tests it with
            // isset()): `false` refreshes the entities as `true` does.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*->setHint\\(Query::HINT_REFRESH, true\\).*'],
            ],
        ],
    ],
];
