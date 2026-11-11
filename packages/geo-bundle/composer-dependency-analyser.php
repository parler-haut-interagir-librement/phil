<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `doctrine/orm`, `twig/twig`, and `symfony/console` with `symfony/http-client-contracts`
 * for `phil:geo:maxmind:update` — are used by the production code but only required in development and suggested:
 * the bundle registers each one only when its package is installed, or a MaxMind database is named.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'twig/twig', 'symfony/console', 'symfony/http-client-contracts'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
