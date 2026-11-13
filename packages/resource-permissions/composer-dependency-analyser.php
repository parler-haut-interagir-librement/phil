<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. One exception: the optional
 * integrations — `doctrine/dbal` for the database store, `doctrine/orm` for the schema listener —, required in
 * development only and suggested: an application that uses one of them installs it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
