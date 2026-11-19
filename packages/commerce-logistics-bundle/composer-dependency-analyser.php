<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The functions of the container configurator that the bundle class calls are defined by
 * `symfony/dependency-injection`, which the analyser does not see as declaring them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
