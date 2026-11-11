<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `twig/twig` for the filters, `symfony/html-sanitizer` for the HTML a template does not
 * mark safe, `symfony/asset-mapper` for the controller and the stylesheet — are used by the production code but only
 * required in development and suggested: the bundle registers its filters only when Twig is installed, sanitizes only
 * when a sanitizer is configured, and maps its assets only when AssetMapper is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'symfony/html-sanitizer', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
