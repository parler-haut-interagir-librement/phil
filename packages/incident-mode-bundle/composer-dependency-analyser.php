<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The console, the forms, Messenger, Scheduler, the ORM's schema event, phil/audit-bundle and phil/privacy-bundle are
 * used by the production code but only required in development and suggested: the bundle registers the commands, the
 * option of the forms, the delayed message, the schedule, the schema listener, the audit and the privacy only when
 * their package is installed.
 *
 * Doctrine DBAL and the Symfony UX Twig components are required without a class of theirs named in the code: the
 * library's store, which the bundle registers, runs on DBAL, and the templates draw the recipes of the kit with the
 * `component` tag.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/audit-bundle',
        'phil/privacy-bundle',
        'symfony/console',
        'symfony/form',
        'symfony/messenger',
        'symfony/options-resolver',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
