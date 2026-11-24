<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Console is used by the production code but only required in development and suggested: the bundle registers the
 * check command only when it is installed. Cache too: `PoolLocality` names the local adapters of the cache component by
 * their class, compared as strings, which loads nothing when it is not installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrorsOnPackages(['symfony/cache', 'symfony/console'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
