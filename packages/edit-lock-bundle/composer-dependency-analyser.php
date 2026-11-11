<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/mercure` for the broadcaster and the hub's subscription URL, `symfony/routing`
 * for the endpoints' URLs, `symfony/asset-mapper` for the controller and stylesheet paths — are used by the production
 * code but only required in development and suggested: the bundle uses them only when they are installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/mercure', 'symfony/routing', 'symfony/asset-mapper'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
