<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * web-token/jwt-library is what Symfony's OpenID Connect pieces verify the tokens with: symfony/security-http only
 * suggests it, so the bundle requires it without naming its classes. `ext-openssl` names no function the code calls,
 * but web-token/jwt-library verifies Google's `RS256` signatures through it while only suggesting it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->addPathToScan(__DIR__.'/config', isDev: false)
    ->ignoreErrorsOnPackages(['web-token/jwt-library'], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-openssl', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
