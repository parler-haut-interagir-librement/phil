<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed or its option set — phil/markup for the bodies,
 * phil/privacy-bundle for the retention periods, phil/sitemap for the pages of the sitemap, whose provider is
 * ordered after the one of phil/sitemap-bundle. `symfony/clock` is required for the `clock` service, named by id;
 * phil/terms-bundle is only named by the route the footer links to. `symfony/ux-twig-component` renders the recipes of
 * the kit the templates call: only the test application names it. `ext-intl` gives the `IntlListFormatter` that
 * twig/intl-extra's `format_list` writes the lists of the pages with.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/markup',
        'phil/privacy-bundle',
        'phil/sitemap',
        'phil/sitemap-bundle',
        'phil/structured-data',
        'phil/structured-data-bundle',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/clock', 'symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::UNUSED_DEPENDENCY]);
