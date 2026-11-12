<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Messenger, the console, the rate limiter, Symfony UX, phil/media-bundle and phil/http-cache-tags-bundle are used by
 * the production code but only required in development and suggested: the bundle registers the queue, the command, the
 * component and the integrations only when their packages are there, and the endpoint of pasted links takes a rate
 * limiter only when the configuration names one.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/http-cache-tags-bundle',
        'phil/media-bundle',
        'symfony/console',
        'symfony/messenger',
        'symfony/rate-limiter',
        'symfony/ux-twig-component',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
