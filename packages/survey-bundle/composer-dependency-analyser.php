<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — Scheduler with Lock and the cache contracts for the schedule,
 * Console for the commands, the ORM for the schema listener, and phil/privacy-bundle for the export and the erasure.
 * The templates call twig/html-extra's html_attr, which the analyser does not read.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/privacy-bundle',
        'symfony/cache-contracts',
        'symfony/console',
        'symfony/lock',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['twig/html-extra'], [ErrorType::UNUSED_DEPENDENCY]);
