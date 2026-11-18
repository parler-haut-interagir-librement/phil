<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `phil/paged-media-bundle`, `symfony/asset-mapper` and `symfony/security-core` are used by the production code but
 * only required in development and suggested: the PDF of a deck is removed by the bundle's pass without the first, the
 * assets are mapped only when the second is installed, and without the third the presenter routes are refused to
 * everyone. `phil/ujo-bundle` is required for its `Ujo` service, which the bundle references by the class of
 * `phil/ujo`; `symfony/ux-twig-component` for the component tag the templates draw their buttons with, which only the
 * functional suite names in PHP.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/paged-media-bundle', 'symfony/asset-mapper', 'symfony/security-core'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['phil/ujo-bundle', 'symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
