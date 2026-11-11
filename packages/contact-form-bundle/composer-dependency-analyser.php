<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `phil/spam-protection-bundle` is required at run time, though the code names none of its classes: the form takes
 * its `spam_protection` option, and the bundle class checks its extension is registered.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('phil/spam-protection-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
