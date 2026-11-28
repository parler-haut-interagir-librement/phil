<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The package's declared dependencies, checked against the package installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    // The adapters of src/Infrastructure, each used only when the application installs the package it adapts.
    ->ignoreErrorsOnPackages([
        'phil/commerce-bundle',
        'phil/email-templates-bundle',
        'phil/paged-media',
        'symfony/mailer',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
