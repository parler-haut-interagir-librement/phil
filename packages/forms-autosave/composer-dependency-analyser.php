<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — Doctrine for the server store, `symfony/key-management` to seal it, the CSRF component to
 * check its requests, Security to name its users, the router for its URLs, the console for its purge command and
 * AssetMapper for the controller's paths — are used by the production code but only required in development and
 * suggested: the bundle uses them only when they are installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/dbal',
        'doctrine/orm',
        'symfony/asset-mapper',
        'symfony/console',
        'symfony/key-management',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
