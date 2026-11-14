<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Doctrine ORM, Symfony Security, AssetMapper, phil/data-table-export and phil/settings-bundle are used by the production code but only
 * required in development and suggested: the bundle registers each adapter only when its package is there.
 * The translator is used by the templates, which are not a scanned path.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'phil/data-table-export', 'phil/settings-bundle', 'symfony/asset-mapper', 'symfony/security-core'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/translation'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
