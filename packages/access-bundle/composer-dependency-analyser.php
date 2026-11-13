<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Two exceptions:
 * `easycorp/easyadmin-bundle`, required in development only and suggested, since the bundle registers its voter only
 * when EasyAdmin is enabled; and `doctrine/dbal`, which no class of the bundle names but which its default store,
 * `DbalTupleStore` of `phil/resource-permissions`, runs on.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['easycorp/easyadmin-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['doctrine/dbal'], [ErrorType::UNUSED_DEPENDENCY]);
