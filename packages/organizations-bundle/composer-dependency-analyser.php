<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/orm` for the schema listener, `phil/privacy-bundle` for the exporter and
 * eraser, `phil/access-bundle`, `phil/resource-permissions` and `phil/resource` for the relations and tuples,
 * `phil/reporting-bundle` for the unit directory of its training reports,
 * `symfony/console` for the commands, `symfony/security-core` for the voter, Twig, the CSRF protection, HttpFoundation,
 * HttpKernel and routing for the administration pages of the units — are used by the production code but only required
 * in development and suggested: the bundle registers each only when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/access-bundle',
        'phil/privacy-bundle',
        'phil/reporting-bundle',
        'phil/resource',
        'phil/resource-permissions',
        'symfony/console',
        'symfony/http-foundation',
        'symfony/http-kernel',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
