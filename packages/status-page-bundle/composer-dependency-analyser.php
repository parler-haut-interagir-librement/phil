<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed or its option chosen — phil/monitoring-bundle for the
 * states of the checks, Flysystem for a "flysystem" target, the cache contracts for keeping the states, the console
 * for the command, Messenger and the Scheduler for pushing in a worker. `symfony/ux-twig-component` renders the recipes
 * of the kit the templates call: only the test application names it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'league/flysystem',
        'phil/monitoring-bundle',
        'symfony/cache-contracts',
        'symfony/console',
        'symfony/messenger',
        'symfony/scheduler',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
