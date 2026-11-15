<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed or its option chosen — Doctrine for the "doctrine" source,
 * the console for the command, Symfony Security and its CSRF protection for the administration page.
 * `symfony/ux-twig-component` renders the recipes of the kit the templates call: only the test application names it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/dbal',
        'doctrine/orm',
        'phil/legal-notices-bundle',
        'symfony/console',
        'symfony/security-core',
        'symfony/security-csrf',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
