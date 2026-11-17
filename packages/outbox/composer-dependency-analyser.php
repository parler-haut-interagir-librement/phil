<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. One exception: PHPUnit, which
 * the contract tests of `src/Test` extend, is required in development only and suggested: a package that tests its
 * store with them has it already.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phpunit/phpunit'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
