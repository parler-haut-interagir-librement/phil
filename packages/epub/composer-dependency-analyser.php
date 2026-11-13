<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * `phil/ujo` is used by the production code under `src/Ujo` but only required in development and suggested: the UJO
 * path is there for applications that store UJO documents, and the rest of the library never loads it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/ujo'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
