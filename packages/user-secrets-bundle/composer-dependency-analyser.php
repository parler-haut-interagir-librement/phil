<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Two exceptions: the optional
 * integrations — `symfony/console` for the two commands, `symfony/lock` for the refresh lock, `phil/privacy-bundle` for the export and erasure of a
 * person's credentials —, required in development only and suggested: the bundle registers them only when the
 * console or the privacy bundle is installed; and `ext-openssl`, which the bundle calls through Symfony's
 * `EnvelopeEncrypter` (AES-256-GCM) without naming one of its functions, and which `symfony/key-management` does not
 * require itself.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/console', 'symfony/lock', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtension('ext-openssl', [ErrorType::UNUSED_DEPENDENCY]);
