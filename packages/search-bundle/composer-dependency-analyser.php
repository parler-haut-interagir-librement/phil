<?php

declare(strict_types=1);

use Loupe\Loupe\LoupeFactory;
use Phil\SearchLoupe\LoupeIndex;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony Console, the HTTP client, Symfony UX Twig Component, phil/search-synonyms and phil/outbox are optional: the
 * commands, the Meilisearch adapter, the filters component, the store of synonyms and the indexing through the outbox
 * are registered only when the application has them and configures them. The test kernel of the outbox calls the
 * functions of the container configurator, which the analyser does not see as declared. The Loupe adapter lives in phil/search-loupe, which the bundle does not
 * require: its classes are only named.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/outbox', 'phil/search-synonyms', 'symfony/console', 'symfony/http-client-contracts', 'symfony/ux-twig-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClasses([LoupeIndex::class, LoupeFactory::class])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
