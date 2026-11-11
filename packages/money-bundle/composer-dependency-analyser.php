<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Two exceptions:
 *
 * - the `service()` function of the container configurator, which the analyser does not see as declared by
 *   `symfony/dependency-injection` although that package defines it;
 * - the optional integrations — `doctrine/dbal`, `doctrine/orm` and `twig/twig` —, which the production code uses
 *   but only requires in development and suggests: the bundle registers each one only when its package is
 *   installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
