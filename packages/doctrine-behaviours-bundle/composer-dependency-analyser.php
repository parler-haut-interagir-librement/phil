<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `doctrine/doctrine-bundle` is required for the services and the tag the bundle names by id — the entity manager,
 * the event listener tag —, while only the test application names one of its classes. `symfony/clock` is required for
 * the `clock` service the listeners take, while only the tests name one of its classes, the test clock.
 * `symfony/security-core` is used by the blame resolver but only required in development and suggested: without it,
 * nobody is blamed. `phil/privacy-bundle` is used by the privacy answers, registered only when it is installed, and is
 * required in development and suggested too, as is `symfony/console`, for the command that strips the versions. `phil/slug`, `symfony/uid` and `symfony/workflow` are required only by the behaviours that name them,
 * and are suggested as well, as is `phil/slug-bundle`, which only the test application names.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/doctrine-bundle', 'symfony/clock'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnPackages(['phil/privacy-bundle', 'phil/slug', 'symfony/console', 'symfony/security-core', 'symfony/uid', 'symfony/workflow'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
