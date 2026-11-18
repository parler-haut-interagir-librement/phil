<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 *
 * A profile is a Composer metapackage: it holds no code, only the requires of its members. Its src/ stays empty — the
 * documentation checks expect the directory of every package — and its tests boot an application with the bundles of
 * the members.
 */
return [
    'paths' => ['tests'],
    'docs' => ['public' => true, 'words' => ['metapackage', 'clamd', 'HSTS']],
];
