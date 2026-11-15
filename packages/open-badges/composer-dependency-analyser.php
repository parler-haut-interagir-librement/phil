<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP and its extensions.
 *
 * `ext-openssl` names no function the code calls, but `web-token/jwt-library` reads RSA keys and signs and verifies
 * `RS256` through it while only suggesting it: the library requires it for its `RS256` keys.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-openssl', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
