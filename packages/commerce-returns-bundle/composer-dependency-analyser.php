<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/config` is imported for the configuration tree, and also needed because the `AbstractBundle` of
 * `symfony/dependency-injection` implements an interface from it. Store credit, sets, specials, shipment dates, slips
 * and the rate limiter are optional: the adapters on `phil/commerce-stored-value-bundle`,
 * `phil/commerce-catalog-bundle`, `phil/commerce-promotions-bundle`, `phil/commerce-logistics-bundle` and
 * `phil/paged-media` are registered only when those are, and a rate limiter only when one is configured, so they stay
 * development dependencies, suggested. The functions of the container configurator that the bundle class calls are defined by
 * `symfony/dependency-injection`, which the analyser does not see as declaring them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/commerce-stored-value-bundle',
        'phil/commerce-catalog-bundle',
        'phil/commerce-promotions-bundle',
        'phil/commerce-logistics-bundle',
        'phil/paged-media',
        'symfony/rate-limiter',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
