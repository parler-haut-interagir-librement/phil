<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/routing` for the editor's endpoints and their URLs, `symfony/asset-mapper` for
 * the controller and stylesheet paths — are used by the production code but only required in development and
 * suggested: the bundle uses them only when they are installed. `phil/ujo-bundle` is required for its services — the
 * `Ujo` and the block type registry the field is built on, referenced by their ids — rather than for a class.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/routing', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('phil/ujo-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
