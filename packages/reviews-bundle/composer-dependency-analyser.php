<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — the ORM for the schema listener, the console for the command,
 * Twig, Routing and CSRF for the pages, Symfony Security for the current member and the responders,
 * phil/moderation-bundle for the reports, phil/privacy-bundle for the export and the erasure,
 * phil/spam-protection-bundle for the review form and phil/structured-data for the AggregateRating.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/moderation-bundle',
        'phil/privacy-bundle',
        'phil/spam-protection-bundle',
        'phil/structured-data',
        'symfony/console',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
