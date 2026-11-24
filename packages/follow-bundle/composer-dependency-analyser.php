<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — Messenger for the bus, Scheduler with Lock and the cache contracts
 * for the schedule, the ORM for the schema listener, and phil/privacy-bundle for the export and the erasure.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/privacy-bundle',
        'symfony/cache-contracts',
        'symfony/lock',
        'symfony/messenger',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
