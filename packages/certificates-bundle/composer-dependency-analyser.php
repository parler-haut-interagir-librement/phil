<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/console` for the commands, `symfony/lock` for the lock the status index of an
 * award is taken under — are used by the production code but only required in development and suggested: the bundle
 * registers the commands only when the Console component is installed, and a lock only when there is a lock factory.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/lock'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
