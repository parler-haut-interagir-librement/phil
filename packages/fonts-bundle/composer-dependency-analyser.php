<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — Twig for `fonts_css()`, `phil/paged-media` for `PdfFonts`, `phil/media-bundle` for
 * `MediaFonts` — are used by the production code but only required in development and suggested: the bundle registers
 * each service only when its classes are installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'phil/paged-media', 'phil/media-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
