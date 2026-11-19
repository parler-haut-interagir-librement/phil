<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The forms, Twig, the routing, the security, the translator, phil/safe-http and phil/analytics-bundle are used by the
 * production code but only required in development and suggested: the bundle registers the administration, the check
 * of a target and the analytics recorder only when they are configured, and refuses the configuration when their
 * packages are missing. The functions of the container configurator are not seen as declared by
 * `symfony/dependency-injection` although that package defines them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/analytics-bundle',
        'phil/safe-http',
        'symfony/form',
        'symfony/options-resolver',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'symfony/translation-contracts',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
