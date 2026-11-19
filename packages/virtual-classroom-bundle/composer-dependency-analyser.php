<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/orm` for the schema listener, `phil/courses-bundle` for the activity type,
 * `phil/privacy-bundle` for the access and erasure requests, `symfony/console` for the commands, `symfony/messenger`
 * for the handler of SyncRoom and `symfony/security-core` for the participant of the authenticated user — are used by
 * the production code but only required in development and suggested: the bundle registers each only when it is
 * installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/courses-bundle',
        'phil/privacy-bundle',
        'symfony/console',
        'symfony/messenger',
        'symfony/security-core',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
