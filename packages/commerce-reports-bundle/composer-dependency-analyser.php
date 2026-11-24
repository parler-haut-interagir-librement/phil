<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The point of sale and the logistics module are used by the production code but only required in development and
 * suggested: the bundle registers the adapter that reads each one only when its repositories are in the container.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/commerce-logistics-bundle',
        'phil/commerce-pos-bundle',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
