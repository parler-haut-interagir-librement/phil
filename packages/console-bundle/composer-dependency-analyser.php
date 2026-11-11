<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Monolog is used by the production code but only required in development and suggested: the bundle registers the
 * debug switch of `--debug-phil` only when Monolog is installed.
 *
 * symfony/event-dispatcher names no class the code uses, but the bundle's listener runs on the kernel's event
 * dispatcher, which the container defines only when the component is a production dependency.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['monolog/monolog'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/event-dispatcher'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
