<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * One exception, and it is the opposite of an omission. `symfony/config` is imported only by the tests, but the
 * `AbstractBundle` of `symfony/dependency-injection` implements an interface from `symfony/config`, which
 * `symfony/dependency-injection` only requires in development: without the declaration, the bundle installed
 * on its own fails to load its own class. The functions of the container configurator that the test kernel
 * calls are defined by `symfony/dependency-injection`, which the analyser does not see as declaring them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/config', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
