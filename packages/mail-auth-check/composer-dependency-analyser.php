<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // ext-intl is suggested: `DomainName` converts internationalised names with it when it is loaded, and refuses them
    // clearly otherwise.
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::DEV_DEPENDENCY_IN_PROD]);
