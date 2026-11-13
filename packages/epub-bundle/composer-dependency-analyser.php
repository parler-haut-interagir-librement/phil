<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — Messenger for the handler, `psr/event-dispatcher` for the event that follows it,
 * `phil/fonts-bundle` and `phil/fonts` for `EpubFonts`, `phil/media-bundle` for the media storage and `MediaAssets` —
 * are used by the production code but only required in development and suggested: the bundle registers each service
 * only when its classes are installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/messenger', 'psr/event-dispatcher', 'phil/fonts', 'phil/fonts-bundle', 'phil/media-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
