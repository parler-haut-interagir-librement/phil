<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * symfony/http-foundation is never named by the code: Symfony's NoPrivateNetworkHttpClient, which wraps the CalDAV
 * client, needs its IpUtils to know the private ranges, and throws without it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/http-foundation', [ErrorType::UNUSED_DEPENDENCY]);
