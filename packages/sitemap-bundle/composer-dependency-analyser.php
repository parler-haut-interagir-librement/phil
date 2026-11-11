<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/clock` is required for the `clock` service the route provider reads `lastmod: now` from, by service id,
 * while only the tests name one of its classes.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
