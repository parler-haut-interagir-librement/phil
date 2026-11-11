<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. One exception: the
 * `service()` and `tagged_iterator()` functions of the container configurator, which the analyser does not see as
 * declared by `symfony/dependency-injection` although that package defines them; and the optional integrations —
 * `psr/cache` for the cache store of circuit states, `symfony/http-client` and its contracts for the clients behind
 * their circuits —, required in development only and suggested.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['psr/cache', 'symfony/http-client', 'symfony/http-client-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
