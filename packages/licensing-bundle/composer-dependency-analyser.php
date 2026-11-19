<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed — the UJO document with phil/ujo, the JSON-LD with
 * phil/structured-data, the voter with symfony/security-core, the forms with symfony/form and symfony/validator —
 * symfony/http-foundation and symfony/options-resolver come with them. `symfony/event-dispatcher` is required without a class
 * of it being named: the kernel defines the `event_dispatcher` service the export guard dispatches through only when
 * an installed package requires it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/paged-media-bundle',
        'phil/structured-data',
        'phil/ujo',
        'symfony/form',
        'symfony/http-foundation',
        'symfony/options-resolver',
        'symfony/security-core',
        'symfony/validator',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/event-dispatcher', [ErrorType::UNUSED_DEPENDENCY]);
