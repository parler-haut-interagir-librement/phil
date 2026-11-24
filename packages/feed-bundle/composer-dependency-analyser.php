<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The content feeds read `phil/content-bundle` and its `phil/locale-dimensions`: used by the production code but only
 * required in development and suggested, since the bundle registers them only when `phil_feed.content` names a feed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/content-bundle',
        'phil/locale-dimensions',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
