<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP, psr/clock and
 * symfony/uid. Doctrine DBAL, PSR-6, Symfony Lock and Symfony Messenger are optional: their adapters are used only
 * when the application has them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'psr/cache', 'symfony/lock', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
