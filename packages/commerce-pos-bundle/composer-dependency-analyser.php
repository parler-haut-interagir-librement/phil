<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/config` is imported for the configuration tree, and also needed because the `AbstractBundle` of
 * `symfony/dependency-injection` implements an interface from it. `symfony/password-hasher` and `symfony/rate-limiter`
 * are used by the production code but only required in development and suggested: the cashier switch by PIN, off by
 * default, needs them only once an application enables it, and the bundle refuses to enable it without them. The
 * functions of the container configurator that the bundle class and the test kernel call are defined by
 * `symfony/dependency-injection`, which the analyser does not see as declaring them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'symfony/password-hasher',
        'symfony/rate-limiter',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
