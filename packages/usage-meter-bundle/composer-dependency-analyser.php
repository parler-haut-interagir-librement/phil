<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/dbal` for the store "dbal", `doctrine/orm` for its schema listener,
 * `phil/commerce-subscription-bundle` for the limits read from subscription features — are used by the production code but only required in development and suggested: the
 * bundle uses each only when the configuration asks for it, or when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'phil/commerce-subscription-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
