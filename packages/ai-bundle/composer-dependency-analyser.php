<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Two exceptions: the optional
 * integrations with `phil/user-secrets-bundle`, for the person's own keys, and with `phil/privacy-bundle`, for privacy
 * requests, required in development only and suggested: the bundle wires each only when its package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/privacy-bundle', 'phil/user-secrets-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
