<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed or its option chosen — Doctrine for the console's store
 * (the models carry its mapping attributes, read only when the store is "doctrine"), phil/monitoring-bundle for the
 * health of a site, Messenger for the work in a worker. `symfony/ux-twig-component` renders the recipes of the kit
 * the templates call: only the test application names it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/monitoring-bundle',
        'symfony/messenger',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
