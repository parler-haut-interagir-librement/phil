<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own. The optional parts — the
 * EasyAdmin links, the privacy module, the stock moves, the commands and the reminders through Messenger — are wired
 * only when their package is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'easycorp/easyadmin-bundle',
        'phil/privacy-bundle',
        'phil/stockroom',
        'phil/stockroom-bundle',
        'symfony/console',
        'symfony/messenger',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // The templates draw the recipes of the kit with the component tag, which the analyser does not read.
    ->ignoreErrorsOnPackages(['symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
