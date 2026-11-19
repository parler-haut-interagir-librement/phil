<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — phil/reservation-bundle's availability, AssetMapper's import map, GD's tiler — are used
 * by the production code but only required in development and suggested: the bundle uses them only when they are
 * installed. Symfony UX Map's Leaflet bridge is required for its options and its controller, which the templates name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/reservation', 'phil/reservation-bundle', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtensions(['ext-gd'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
