<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The functions of the container configurator that the bundle class calls are defined by
 * `symfony/dependency-injection`, which the analyser does not see as declaring them.
 *
 * `symfony/messenger`, for the handler of `ProducePrintFiles` and the listener that dispatches it, is used by the
 * production code but only required in development and suggested: the bundle registers them only when Messenger is
 * installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ])
    ->ignoreErrorsOnPackages(['symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
