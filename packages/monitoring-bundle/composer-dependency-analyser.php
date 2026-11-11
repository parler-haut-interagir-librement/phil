<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. The optional integrations —
 * DBAL and Flysystem for their checks, Scheduler for the heartbeats of scheduled messages, Routing, Security, CSRF and
 * Twig for the maintenance page and the page of the queues — are required in development only and suggested.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/dbal',
        'league/flysystem',
        'symfony/routing',
        'symfony/scheduler',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
