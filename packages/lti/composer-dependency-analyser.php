<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * OpenSSL is required at run time: `web-token/jwt-library`, which only suggests it, makes and checks the RSA signatures
 * and reads the keys in PEM through it. The code of the package calls it in its tests only.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-openssl', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
