<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/orm` for the schema listener, `phil/privacy-bundle` for the exporter and
 * eraser, `phil/access-bundle`, `phil/resource-permissions` and `phil/resource` for the relations and tuples,
 * `symfony/console` for the commands, `symfony/security-core` for the voter — are used by the production code but only
 * required in development and suggested: the bundle registers each only when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/access-bundle',
        'phil/privacy-bundle',
        'phil/resource',
        'phil/resource-permissions',
        'symfony/console',
        'symfony/security-core',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
