<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. The adapters —
 * `doctrine/dbal` for the database store, `doctrine/orm` for the schema listener, `symfony/messenger` for the delayed
 * lifting — are required in development only and suggested: an application that uses one of them installs it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
