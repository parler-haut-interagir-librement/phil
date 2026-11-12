<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/asset-mapper`, for the path of the screen's Stimulus controller, and `easycorp/easyadmin-bundle`, for the
 * menu links to the glossary pages, are used by the production code but only required in development and suggested:
 * the bundle uses each only when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['easycorp/easyadmin-bundle', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
