<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/config` is imported for the configuration tree, and also needed because the `AbstractBundle` of
 * `symfony/dependency-injection` implements an interface from it. The functions of the container configurator that
 * the bundle class calls are defined by `symfony/dependency-injection`, which the analyser does not see as declaring
 * them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
