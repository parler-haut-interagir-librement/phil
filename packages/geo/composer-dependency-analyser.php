<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP alone.
 *
 * The geocoding adapters use `symfony/http-client-contracts` and `symfony/cache-contracts`, required in development
 * and suggested: the value types and the ports need neither, and an application that geocodes installs them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/http-client-contracts', 'symfony/cache-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
