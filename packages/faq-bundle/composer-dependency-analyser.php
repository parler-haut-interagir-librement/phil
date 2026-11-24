<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony Console, Yaml, Security, Translation and EasyAdmin are used by the production code but only required in
 * development and suggested: the bundle registers the commands, the administration and its menu only when their
 * packages are there.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['easycorp/easyadmin-bundle', 'symfony/console', 'symfony/security-core', 'symfony/translation', 'symfony/yaml'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
