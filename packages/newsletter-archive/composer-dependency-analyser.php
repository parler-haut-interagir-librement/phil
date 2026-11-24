<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/console` for the command, `doctrine/orm` for the schema listener — are used by
 * the production code but only required in development and suggested: the bundle registers the command only when the
 * console is installed, and the listener is called only by the ORM.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'doctrine/orm'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // Declared by the file of ContainerConfigurator, which no autoloader names.
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service']);
