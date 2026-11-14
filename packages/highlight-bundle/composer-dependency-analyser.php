<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `twig/twig` and `phil/ujo` — are used by the production code but only required in
 * development and suggested: the bundle registers its filter only when Twig is installed and colours the code blocks
 * of UJO documents only when `phil/ujo-bundle`'s renderer is in the container. AssetMapper is reached by the name of
 * its extension alone, so its stylesheet mapping needs no class of `symfony/asset-mapper`.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'phil/ujo'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
