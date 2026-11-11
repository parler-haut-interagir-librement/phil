<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `twig/twig`, and `doctrine/dbal`, `doctrine/orm` and `psr/clock` for the history — are
 * used by the production code but only required in development and suggested: the bundle registers its filter only
 * when Twig is installed, and the history only when it is enabled.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'psr/clock', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
