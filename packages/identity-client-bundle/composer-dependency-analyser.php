<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * - `web-token/jwt-library` and the HTTP client are what the `oidc_login` authenticator of the security bundle runs on,
 *   and FrameworkBundle gives the kernel it lives in: only the tests name them.
 * - The `service()` function lives in the same file as `ContainerConfigurator`, loaded with the class and not by
 *   Composer's autoloader.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['web-token/jwt-library', 'symfony/http-client', 'symfony/framework-bundle', 'symfony/security-bundle', 'symfony/cache'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service']);
