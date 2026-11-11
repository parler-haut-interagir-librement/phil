<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * `symfony/http-client-contracts` is used by `Breach\Adapter\PwnedPasswordsSource` and `symfony/validator` by the
 * constraints under `Validator`; both are only required in development and suggested: the estimator, the generators
 * and the breach checker never load them, and an application that checks breaches over HTTP or validates forms
 * installs them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/http-client-contracts', 'symfony/validator'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
