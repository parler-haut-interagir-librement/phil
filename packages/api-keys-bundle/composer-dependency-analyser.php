<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `phil/api-contract-bundle` and `phil/api-contract` for the problem details,
 * `phil/rate-limit-bundle` for the key of the rate limiters, `phil/ip-anonymizer` for the address of the last
 * use, `symfony/console` for the commands, `symfony/form`, `symfony/security-csrf`, `symfony/routing`,
 * `symfony/options-resolver`, `symfony/translation`, `symfony/validator` and `twig/twig` for the members' page,
 * `phil/url`, `symfony/cache-contracts` and `symfony/http-client-contracts` for the endpoint of GitHub's secret
 * scanning —
 * are used by the production code but only required in development and suggested: the bundle registers each only when
 * it is installed, or when the configuration asks for it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/api-contract',
        'phil/api-contract-bundle',
        'phil/ip-anonymizer',
        'phil/rate-limit-bundle',
        'phil/url',
        'symfony/cache-contracts',
        'symfony/console',
        'symfony/form',
        'symfony/http-client-contracts',
        'symfony/options-resolver',
        'symfony/routing',
        'symfony/security-csrf',
        'symfony/translation',
        'symfony/validator',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
