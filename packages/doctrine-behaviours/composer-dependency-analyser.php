<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP, doctrine/collections
 * for the criteria of the tree, doctrine/dbal for the column types, doctrine/orm and psr/clock. phil/slug, symfony/uid
 * and symfony/workflow serve the behaviours that name them, and are suggested: an application that uses none of those
 * does not install them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/slug', 'symfony/uid', 'symfony/workflow'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
