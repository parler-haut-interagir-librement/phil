<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * DBAL, the ORM, the console, Twig, AssetMapper, the rate limiter, PSR-3, PSR-6, security-http, phil/ip-anonymizer and
 * phil/rate-limit-bundle are used by the production code but only required in development and suggested: the bundle
 * wires the store, the schema listener, the command, the Twig functions, the nonce of importmap(), the throttle, the
 * logger, the deduplication, the logout listener, the truncated address and the named limiter only when their packages
 * are there. The `service()` function of the container configurator is not seen as declared by
 * `symfony/dependency-injection` although that package defines it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/dbal',
        'doctrine/orm',
        'phil/ip-anonymizer',
        'phil/rate-limit-bundle',
        'psr/cache',
        'psr/log',
        'symfony/asset-mapper',
        'symfony/console',
        'symfony/rate-limiter',
        'symfony/security-http',
        'symfony/twig-bridge',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
