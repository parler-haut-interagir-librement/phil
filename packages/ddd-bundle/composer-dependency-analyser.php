<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Two exceptions.
 *
 * - The `service()` function lives in the same file as `ContainerConfigurator`, from
 *   `symfony/dependency-injection`, which is required: it is loaded with the class, not by Composer's
 *   autoloader, so the tool cannot attach it to a package.
 * - `doctrine/dbal` is optional: in `require-dev` for the tests, in `suggest` for applications, with a
 *   `conflict` that rules out the versions the type is not written for. Only `Infrastructure/Doctrine`
 *   imports it, and nothing loads that directory unless the application registers a type from it, which
 *   means the application already requires DBAL. The exception is scoped to that directory, so an import of
 *   DBAL anywhere else in the bundle still fails.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service'])
    ->ignoreErrorsOnPackageAndPath(
        'doctrine/dbal',
        __DIR__.'/src/Infrastructure/Doctrine',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    );
