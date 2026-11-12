<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * `StoreLocality` names the local adapters of the cache component and the storages of the rate limiter by their class,
 * compared as strings, which loads nothing when they are not installed: both are only required in development, the
 * rate limiter suggested.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'symfony/cache',
        'symfony/rate-limiter',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
