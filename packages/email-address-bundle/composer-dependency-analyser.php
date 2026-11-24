<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — the Form component and what its field type uses, the Console, Messenger and Symfony's
 * RemoteEvent — are used by the production code but only required in development and suggested: the bundle
 * registers each one only when its package is installed. `symfony/routing` reads `config/routes.php`, which is not
 * scanned. The functions of the container configurator are not seen as declared by `symfony/dependency-injection`
 * although that package defines them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'symfony/console',
        'symfony/form',
        'symfony/messenger',
        'symfony/options-resolver',
        'symfony/remote-event',
        'symfony/translation-contracts',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
