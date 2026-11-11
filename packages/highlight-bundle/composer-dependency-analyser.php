<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `twig/twig`, `phil/ujo` and `symfony/asset-mapper` — are used by the production code
 * but only required in development and suggested: the bundle registers its filter only when Twig is installed, colours
 * the code blocks of UJO documents only when `phil/ujo-bundle`'s renderer is in the container, and maps its stylesheet
 * only when AssetMapper is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'phil/ujo', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
