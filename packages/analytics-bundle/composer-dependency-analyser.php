<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/dbal` and `doctrine/orm` for the default counter store, `symfony/lock` for the
 * salt, `symfony/scheduler` for the daily jobs, `phil/broken-flow-alerts` for the flow check — are required in
 * development only and suggested: their classes are wired only when the application installs them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'phil/broken-flow-alerts', 'symfony/lock', 'symfony/scheduler'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
