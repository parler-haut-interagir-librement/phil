<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * `phil/spam-protection-bundle` is required at run time, though the code names none of its classes: the form takes
 * its `spam_protection` option, and the bundle class checks its extension is registered. The mailer, the HTTP client
 * and the console are optional: their services are registered only when configured or installed; so are
 * `phil/privacy-bundle`, whose access and erasure requests find the entries, `symfony/security-core`, whose
 * signed-in user names the person sending a form, `symfony/key-management`, whose envelope encryption seals the
 * values of sensitive fields, and `phil/form-logic`, whose Stimulus controller applies the conditions in the browser
 * when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('phil/spam-protection-bundle', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnPackages(['symfony/mailer', 'symfony/twig-bridge', 'symfony/http-client', 'symfony/http-client-contracts', 'symfony/console', 'symfony/translation-contracts', 'phil/privacy-bundle', 'symfony/security-core', 'symfony/key-management', 'phil/form-logic'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
