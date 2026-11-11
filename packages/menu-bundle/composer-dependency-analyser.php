<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Twig is used by the production code but only required in development and suggested: the bundle registers its
 * extension only when Twig is installed. The extension takes the translator and the router when the application has
 * them, so `symfony/routing` and `symfony/translation-contracts` are suggested in the same way.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/routing', 'symfony/translation-contracts', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
