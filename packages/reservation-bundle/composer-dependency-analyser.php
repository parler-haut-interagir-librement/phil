<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The calendar, the console and Messenger are used by the production code but only required in development and
 * suggested: the bundle registers the calendar's item source, the commands and the Messenger sender only when their
 * packages are there.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/calendar-bundle', 'symfony/console', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
