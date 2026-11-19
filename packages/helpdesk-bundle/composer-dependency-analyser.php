<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — the ORM for the schema listener, EasyAdmin for the pages grafted on
 * its dashboards, Twig, Routing, CSRF and the Twig components for the pages and the canned replies, Symfony Security
 * for the agents named by role, phil/notifications-bundle for the notifications and phil/privacy-bundle for the export
 * and the erasure.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'easycorp/easyadmin-bundle',
        'phil/notifications-bundle',
        'phil/privacy-bundle',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
