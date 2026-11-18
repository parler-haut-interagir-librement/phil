<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. One exception:
 * `easycorp/easyadmin-bundle` and `phil/tenancy-bundle`, required in development only and suggested, since the bundle
 * registers its voter only when EasyAdmin is enabled and partitions the tuples by tenant only when the tenancy bundle
 * is.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['easycorp/easyadmin-bundle', 'phil/tenancy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
