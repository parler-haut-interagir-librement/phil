<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Twig, `phil/almanac`, `phil/paged-media`, the console and Symfony UX are used by the production code but only
 * required in development and suggested: the bundle registers Twig's extension, the almanac's source and command, the
 * printed calendar and the component only when their packages are there; the paths of AssetMapper are written only
 * when its bundle is registered. `phil/structured-data` receives the events `calendar_event_json_ld` adds to the page's
 * graph, when phil/structured-data-bundle provides one.
 * `phil/privacy-bundle` receives the calendar's part of a member's export and erasure when it is installed.
 * `phil/recurrence-bundle` gives the column type and the field the events and the form use by name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/almanac', 'phil/paged-media', 'phil/privacy-bundle', 'phil/resource', 'phil/structured-data', 'phil/taxonomy-bundle', 'twig/twig', 'symfony/console', 'symfony/ux-live-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
