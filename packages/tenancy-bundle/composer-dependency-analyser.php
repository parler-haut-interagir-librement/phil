<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `symfony/cache`, `symfony/console`, `symfony/mailer`, `symfony/messenger`, `symfony/security-http` and `twig/twig`
 * are suggested, not required: the cache pools scoped to the tenant, the `--tenant` option, the mail of each tenant,
 * the Messenger middleware, the scoped sessions and the Twig functions are registered only when their component is
 * installed, and their classes are loaded only then; `psr/cache` and `symfony/cache-contracts` come with `symfony/cache`;
 * `symfony/mime` and `symfony/security-core` come with them — the token storage of the latter, which tells a visitor
 * from a signed-in account, is read only when SecurityBundle registers it. The configurator functions of `symfony/dependency-injection` are declared in a
 * file Composer does not autoload by name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['psr/cache', 'symfony/cache', 'symfony/cache-contracts', 'symfony/console', 'symfony/mailer', 'symfony/messenger', 'symfony/mime', 'symfony/security-core', 'symfony/security-http', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service_locator',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
