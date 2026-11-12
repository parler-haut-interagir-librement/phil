<?php

declare(strict_types=1);

use Loupe\Loupe\LoupeFactory;
use Phil\SearchLoupe\LoupeIndex;
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony Console, the HTTP client, Symfony UX Twig Component and phil/search-synonyms are optional: the commands, the
 * Meilisearch adapter, the filters component and the store of synonyms are registered only when the application has
 * them and configures them. The Loupe adapter lives in phil/search-loupe, which the bundle does not
 * require: its classes are only named.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/search-synonyms', 'symfony/console', 'symfony/http-client-contracts', 'symfony/ux-twig-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClasses([LoupeIndex::class, LoupeFactory::class]);
