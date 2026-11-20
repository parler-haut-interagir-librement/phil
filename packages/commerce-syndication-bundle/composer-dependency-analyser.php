<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/config` is imported for the configuration tree, and also needed because the `AbstractBundle` of
 * `symfony/dependency-injection` implements an interface from it. The console command and the HTTP transport are
 * optional: the bundle registers them only when `symfony/console` and the HTTP client are installed, so their
 * packages stay development dependencies, suggested. The functions of the container configurator that the bundle
 * class calls are defined by `symfony/dependency-injection`, which the analyser does not see as declaring them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/http-client-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
