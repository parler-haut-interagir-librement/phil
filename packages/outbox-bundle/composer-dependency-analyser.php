<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed or enabled — the ORM for the schema listener, the console for the
 * commands, the lock for one run of the relay at a time, Messenger for the bridge to a bus and, with the scheduler, for
 * the scheduled trigger.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'symfony/console',
        'symfony/lock',
        'symfony/messenger',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
