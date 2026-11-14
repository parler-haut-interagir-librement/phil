<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/console`, `symfony/messenger` and `symfony/process` are suggested, not required: the commands and the
 * Messenger middleware are registered only when their component is installed, and their classes are loaded only then.
 * The configurator functions of `symfony/dependency-injection` are declared in a file Composer does not autoload by name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/messenger', 'symfony/process'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions(['Symfony\Component\DependencyInjection\Loader\Configurator\service']);
