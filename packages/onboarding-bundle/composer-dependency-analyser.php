<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — Twig for the checklist and the tour, Routing for the URLs of the
 * endpoints, the Twig components, AssetMapper for the controller, Symfony Security for the current user, and
 * phil/settings-bundle for the preference that turns the tours off.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/privacy-bundle',
        'phil/settings-bundle',
        'symfony/asset-mapper',
        'symfony/routing',
        'symfony/security-core',
        'symfony/ux-twig-component',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
