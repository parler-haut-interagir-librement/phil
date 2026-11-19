<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The ORM, EasyAdmin, Monolog, the console, Messenger, the rate limiter, routing, security, CSRF, Twig, phil/monitoring-bundle,
 * phil/notifications-bundle and phil/rate-limit-bundle are used by the production code but only required in development
 * and suggested: the bundle wires the schema listener, the log handler, the command and the console listener, the
 * worker listener and the queued alerts, the throttles, the administration pages, the health check, the notified
 * alerts and the named limiter only when their packages are there. The `service()` function of the container configurator is
 * not seen as declared by `symfony/dependency-injection` although that package defines it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'easycorp/easyadmin-bundle',
        'monolog/monolog',
        'phil/monitoring-bundle',
        'phil/notifications-bundle',
        'phil/rate-limit-bundle',
        'symfony/console',
        'symfony/messenger',
        'symfony/rate-limiter',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
