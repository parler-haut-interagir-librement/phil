<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — Messenger for the bus, Mercure for live toasts, Routing for the
 * endpoints' URLs, Scheduler, Console, Mailer and the Twig bridge for the schedule, the purge command and the digest,
 * the Twig components, the ORM for the schema listener, phil/standard-webhooks for receiving from a network, and
 * minishlink/web-push with Symfony HttpClient and Asset for Web Push.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'minishlink/web-push',
        'phil/standard-webhooks',
        'symfony/asset',
        'symfony/console',
        'symfony/http-client',
        'symfony/http-client-contracts',
        'symfony/mailer',
        'symfony/mercure',
        'symfony/messenger',
        'symfony/routing',
        'symfony/scheduler',
        'symfony/twig-bridge',
        'symfony/ux-twig-component',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtensions(['ext-openssl'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
