<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `phil/taxonomy-bundle` and `phil/resource` are used by the production code but only required in development and
 * suggested: the bundle files products in the taxonomy only when its package is there. So are `phil/product-feeds`,
 * with the `phil/url` it builds addresses with, `symfony/console`, `symfony/messenger`, `symfony/http-foundation` and
 * `symfony/http-kernel`: the bundle publishes feeds only when the library is there, and declares their command, handler
 * and controller only when Console, Messenger and HttpFoundation are.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/product-feeds', 'phil/resource', 'phil/taxonomy-bundle', 'phil/url', 'symfony/console', 'symfony/http-foundation', 'symfony/http-kernel', 'symfony/messenger'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
