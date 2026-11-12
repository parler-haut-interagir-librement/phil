<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — Doctrine, with `symfony/uid` for the stored dates' identifiers, and Twig — are used by
 * the production code but only required in development and suggested: the bundle registers each one only when it is
 * asked for or installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'symfony/uid', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
