<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Three exceptions.
 *
 * - The `service()` function lives in the same file as `ContainerConfigurator`, from
 *   `symfony/dependency-injection`, which is required: it is loaded with the class, not by Composer's
 *   autoloader, so the tool cannot attach it to a package.
 * - `doctrine/dbal` is optional: in `require-dev` for the tests, in `suggest` for applications, with a
 *   `conflict` that rules out the versions the type is not written for. Only `Infrastructure/Doctrine`
 *   imports it, and nothing loads that directory unless the application registers a type from it, which
 *   means the application already requires DBAL. The exception is scoped to that directory, so an import of
 *   DBAL anywhere else in the bundle still fails.
 * - `symfony/http-kernel` (with `symfony/http-foundation`, which it requires) and `symfony/serializer` are optional
 *   the same way: each is imported by one directory, `Infrastructure/HttpKernel` and `Infrastructure/Serializer`,
 *   whose service the bundle registers only when the component is installed — the bundle class names them to check.
 * - `symfony/maker-bundle` is optional the same way: only `Infrastructure/Maker` imports it, with `symfony/console`,
 *   which MakerBundle requires, and the bundle registers the `make:ddd:*` commands only when MakerBundle is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service'])
    ->ignoreErrorsOnPackageAndPath(
        'doctrine/dbal',
        __DIR__.'/src/Infrastructure/Doctrine',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPaths(
        'symfony/http-kernel',
        [__DIR__.'/src/Infrastructure/HttpKernel', __DIR__.'/src/PhilDddBundle.php'],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPath(
        'symfony/http-foundation',
        __DIR__.'/src/Infrastructure/HttpKernel',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPaths(
        'symfony/serializer',
        [__DIR__.'/src/Infrastructure/Serializer', __DIR__.'/src/PhilDddBundle.php'],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPath(
        'symfony/console',
        __DIR__.'/src/Infrastructure/Maker',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPaths(
        'symfony/maker-bundle',
        [__DIR__.'/src/Infrastructure/Maker', __DIR__.'/src/PhilDddBundle.php'],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    );
