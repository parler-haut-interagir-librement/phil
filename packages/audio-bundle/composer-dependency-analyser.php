<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `phil/media-bundle` and `symfony/ux-twig-component` — are used by the production code
 * but only required in development and suggested: the bundle registers each one only when its package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/media-bundle', 'symfony/ux-twig-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
