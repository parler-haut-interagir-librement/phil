<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. One exception: the optional
 * integrations — `symfony/console` for the commands, `symfony/scheduler` for the schedule, `doctrine/persistence` for
 * the register drawn from every Doctrine entity, `doctrine/dbal` and `doctrine/orm` for anonymization and the tables,
 * `symfony/rate-limiter`, `symfony/mailer` and `symfony/mime` for the requests made without an account, and
 * `phpunit/phpunit` for the test helper of `src/Infrastructure/Testing` —, required in development only and
 * suggested: the bundle registers the commands only when the console is installed, the database services only when
 * DBAL is, the requests without an account only when they are enabled, refusing the build without the rate limiter,
 * and asks for the registry only when the container has one.
 *
 * `ext-zlib` compresses the files of an export archive when it is there; `ZipFile` stores them without it.
 *
 * And one requirement the code does not import: `symfony/clock`. The bundle reads the PSR-20 clock through the service
 * `clock`, which FrameworkBundle defines only when the component is installed; without it, the account deletions and
 * the pages, on by default, would be taken out of the container.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/scheduler', 'doctrine/persistence', 'doctrine/dbal', 'doctrine/orm', 'symfony/rate-limiter', 'symfony/mailer', 'symfony/mime', 'phpunit/phpunit'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnExtension('ext-zlib', [ErrorType::SHADOW_DEPENDENCY]);
