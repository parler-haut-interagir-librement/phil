<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/dbal` and `twig/twig` — are used by the production code but only required in
 * development and suggested: the bundle registers each one only when its package is installed. `symfony/routing` reads
 * `config/routes.php`, which is not scanned.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
