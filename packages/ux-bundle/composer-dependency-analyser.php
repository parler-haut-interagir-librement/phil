<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/asset-mapper` is used by the production code but optional: the stylesheet is mapped only with AssetMapper.
 *
 * phil/ux-css, the Form component, Twig and its bundle are what the bundle is for, but only its template and the
 * Composer path of the stylesheet use them, and the analyser reads PHP alone: it takes phil/ux-css for a package
 * nothing uses, the others for packages only the tests use.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['phil/ux-css'], [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackages(['symfony/form', 'symfony/twig-bundle', 'twig/twig'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
