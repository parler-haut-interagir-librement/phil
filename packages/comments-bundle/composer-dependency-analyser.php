<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/routing` for the endpoints' URLs, Symfony UX Twig Component with Twig and the
 * translator for the comments on a whole page drawn by the server — are used by the production code but only required
 * in development and suggested: the bundle uses them only when they are installed. The paths of AssetMapper are
 * written to its extension by name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/routing', 'symfony/ux-twig-component', 'twig/twig', 'symfony/translation-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
