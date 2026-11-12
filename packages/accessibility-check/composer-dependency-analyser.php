<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * `phil/ujo`, `symfony/validator`, `symfony/workflow` and `symfony/console` are used by the production code but only
 * required in development and suggested: the UJO reading, the `AccessibleContent` constraint, the `PublicationGuard`
 * and the command are there for applications that have them, and the HTML checker never loads them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/ujo', 'symfony/validator', 'symfony/workflow', 'symfony/console'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
