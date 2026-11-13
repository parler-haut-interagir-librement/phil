<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. Two exceptions, required in
 * development only and suggested: Doctrine DBAL, which the database store alone uses, and Symfony Messenger, which the
 * reminder dispatcher and handler alone use.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
