<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * phil/ux-css and AssetMapper are what the bundle is for, but the analyser reads PHP alone: `ThemeSheet` finds the
 * tokens of phil/ux-css by Composer's paths, and the stylesheet is mapped through FrameworkBundle's configuration.
 *
 * The test kernel boots the bundles EasyAdmin needs; their packages are installed with EasyAdmin itself.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/ux-css'], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackages(['symfony/asset-mapper'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
