<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. One exception: the optional
 * integrations — `doctrine/dbal` and `doctrine/orm` for the table of the trail, `symfony/console` for the three
 * commands, `symfony/http-client-contracts` and `phil/url` for the OpenTelemetry exporter, `symfony/messenger` for the dispatch of rows and the feed after each one, `symfony/security-core` for the current account,
 * `symfony/workflow` for the transitions and `phil/tenancy-bundle` for the tenant —, required in development only and
 * suggested: the bundle registers each only when it is installed or configured.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/dbal', 'doctrine/orm', 'phil/tenancy-bundle', 'phil/url', 'symfony/console', 'symfony/http-client-contracts', 'symfony/messenger', 'symfony/security-core', 'symfony/workflow'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
