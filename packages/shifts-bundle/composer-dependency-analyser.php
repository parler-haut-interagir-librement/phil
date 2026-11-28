<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — Console for the command, the ORM for the schema listener, Forms
 * for the coordinators' form, Messenger for the allocation in a worker, the Twig components, and phil/privacy-bundle
 * for the export and the erasure.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/privacy-bundle',
        'symfony/console',
        'symfony/form',
        'symfony/messenger',
        'symfony/options-resolver',
        'symfony/ux-twig-component',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
