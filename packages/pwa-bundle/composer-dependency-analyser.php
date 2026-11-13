<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The Asset component is used by the production code but only required in development and suggested: the bundle gives
 * the addresses of assets through it only when it is installed, and from their logical paths otherwise. The controllers
 * are run by `symfony/http-kernel`, which the code does not name; it also brings the event dispatcher a kernel needs.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/asset', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/http-kernel', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
