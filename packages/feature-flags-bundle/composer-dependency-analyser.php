<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. One exception: the
 * `service()` function of the container configurator, which the analyser does not see as declared by
 * `symfony/dependency-injection` although that package defines it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
