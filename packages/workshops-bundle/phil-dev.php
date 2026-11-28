<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['waitlist']],
    'deptrac' => [
        // The repositories compare moments as Doctrine DBAL's datetime_utc_immutable type; PHP's own sort direction,
        // which the ORM takes, comes from symfony/polyfill-php86 on PHP 8.5, in the global namespace that the default
        // layers do not cover.
        'allow' => ['^Doctrine\\\\DBAL\\\\.*', '^SortDirection$'],
    ],
];
