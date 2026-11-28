<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * `phil/publishing` is used by the listener of `Bridge\Publishing` but only required in development and suggested: an
 * application that has it registers the listener, and the rest of the library never loads it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/publishing'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
