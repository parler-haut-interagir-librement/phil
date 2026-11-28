<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. The waitlist bundle is
 * suggested: only the integration and the bundle class, which registers it when it is installed, name it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPath(__DIR__.'/src/Integration', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['phil/waitlist-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
