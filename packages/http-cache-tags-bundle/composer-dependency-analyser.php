<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * - Doctrine ORM, the HTTP client and Twig are optional: their services are registered only when the application
 *   has them and configures them. A PSR-3 logger is optional too: a refused purge is logged when there is one;
 *   and Messenger, whose worker events are only named in listener tags.
 * - The `service()` function the test kernel wires with lives in the same file as `ContainerConfigurator`, from
 *   `symfony/dependency-injection`, which is required: it is loaded with the class, not by Composer's autoloader.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'psr/log', 'symfony/http-client-contracts', 'symfony/messenger', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service']);
