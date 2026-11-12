<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Doctrine DBAL and ORM, the HTTP client, PSR-6 and PSR-3, Symfony Console, Security and Yaml are optional: their services
 * are used only when the application has them. TraceableAdapterEvent, which a test reads, is declared in the file of
 * TraceableAdapter and cannot be autoloaded on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'symfony/http-client', 'symfony/http-client-contracts', 'psr/cache', 'psr/log', 'symfony/console', 'symfony/security-core', 'symfony/yaml'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClasses(['Symfony\\Component\\Cache\\Adapter\\TraceableAdapterEvent']);
