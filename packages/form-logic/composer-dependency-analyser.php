<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. One exception: the optional
 * integration with `phil/forms-bundle`, whose port `JsonLogicConditions` implements, required in development only and
 * suggested: an application that uses it installs the bundle.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/forms-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
