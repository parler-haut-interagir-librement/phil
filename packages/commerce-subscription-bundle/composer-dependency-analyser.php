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
 * The console, the lock, Scheduler and phil/privacy-bundle are used by the production code but only required in
 * development and suggested: the bundle registers the purge command, the lock, the schedule and the privacy part only
 * when their package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ])
    ->ignoreErrorsOnPackages([
        'phil/privacy-bundle',
        'symfony/console',
        'symfony/lock',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
