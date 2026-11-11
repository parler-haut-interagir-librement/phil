<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `doctrine/doctrine-migrations-bundle` is required for the services the bundle wraps — its dependency factory, by
 * service id —, while only the test application names one of its classes, to register it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('doctrine/doctrine-migrations-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
