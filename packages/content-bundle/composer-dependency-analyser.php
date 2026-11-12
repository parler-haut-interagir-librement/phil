<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Two exceptions. The `service()` and `tagged_iterator()` functions live in the same file as
 * `ContainerConfigurator`, from `symfony/dependency-injection`, which is required: they are loaded with the
 * class, not by Composer's autoloader, so the tool cannot attach them to a package. And `symfony/security-core` is
 * optional: `RawHtmlRole` is registered only when `raw_html_role` names the trusted role that publishes raw HTML.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // Used by the production code but only required in development and suggested: the bundle registers the raw HTML
    // role, the administration and its menu, the schedule of publications and the search feed only when their packages
    // are there.
    ->ignoreErrorsOnPackages([
        'easycorp/easyadmin-bundle',
        'phil/resource',
        'phil/scheduled-transitions-bundle',
        'phil/search-bundle',
        'symfony/security-core',
        'symfony/security-csrf',
        'symfony/translation',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
