<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. Two exceptions: Doctrine
 * DBAL, for the database stores, required in development only and suggested: an application that keeps its
 * definitions and runs in a database installs it; and `symfony/http-foundation`, which the code never names but which
 * `NoPrivateNetworkHttpClient` needs to check addresses.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/http-foundation'], [ErrorType::UNUSED_DEPENDENCY]);
