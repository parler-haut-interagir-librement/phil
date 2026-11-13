<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * The Gotenberg client is an optional part: it speaks through `symfony/http-client-contracts`, required in development
 * and suggested, so the native writer keeps no dependency.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/http-client-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
