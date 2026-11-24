<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 *
 * `phil/structured-data`, `phil/social-cards` and `phil/link-checker` are used by the classes of `Bridge\` but only
 * required in development and suggested: an application that has them adds those classes, and the others never load
 * them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/structured-data', 'phil/social-cards', 'phil/link-checker'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
