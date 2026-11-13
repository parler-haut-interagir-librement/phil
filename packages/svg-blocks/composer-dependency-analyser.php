<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * `symfony/http-client-contracts` is used by the production code under `src/Http` but only required in development
 * and suggested: `HttpMermaidService` is there for applications that call the Mermaid service, and the rest of the
 * package never loads it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/http-client-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
