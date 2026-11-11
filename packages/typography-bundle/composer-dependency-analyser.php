<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `twig/twig` and `phil/ujo` — are used by the production code but only required in
 * development and suggested: the bundle registers its filters only when Twig is installed, and its pass over the HTML
 * of UJO documents only when `phil/ujo-bundle` is.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'phil/ujo'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
