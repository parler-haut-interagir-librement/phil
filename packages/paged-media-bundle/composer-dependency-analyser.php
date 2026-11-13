<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/messenger` for the handler of the messages, `psr/event-dispatcher` for the
 * event that follows them, `psr/cache` for the job store in a cache pool — are used by the production code but only
 * required in development and suggested: the bundle registers its handler only when Messenger is installed,
 * dispatches its event only when an event dispatcher is in the container, and keeps its jobs in a pool only when one
 * is configured.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/messenger', 'psr/event-dispatcher', 'psr/cache', 'phil/footnotes-sidenotes', 'phil/ujo'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
