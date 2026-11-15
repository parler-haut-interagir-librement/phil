<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — phil/automations, phil/privacy-bundle, phil/standard-webhooks-bundle, Symfony's
 * RemoteEvent, the Console and the ORM's schema listener — are used by the production code but only required in
 * development and suggested: the bundle registers each one only when its package is installed. The translator is
 * used by the templates only, which the analysis does not read. The functions of the container configurator are not
 * seen as declared by `symfony/dependency-injection` although that package defines them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/automations',
        'phil/privacy-bundle',
        'phil/standard-webhooks-bundle',
        'symfony/console',
        'symfony/remote-event',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/translation'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
