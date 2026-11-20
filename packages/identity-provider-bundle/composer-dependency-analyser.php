<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `phil/audit-bundle` for the audit trail, `symfony/console` for the commands,
 * `symfony/key-management` for the encryption of secrets, `symfony/mailer`, `symfony/mime`, `symfony/twig-bridge` and
 * `symfony/translation-contracts` for the emails, `symfony/notifier` for the SMS codes, `symfony/messenger` and
 * `symfony/http-client-contracts` for the back-channel logout — are used by the production
 * code but only required in development and suggested: the bundle registers each only when it is installed and the
 * configuration asks for it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/audit-bundle',
        'symfony/console',
        'symfony/http-client-contracts',
        'symfony/key-management',
        'symfony/mailer',
        'symfony/messenger',
        'symfony/mime',
        'symfony/notifier',
        'symfony/translation-contracts',
        'symfony/twig-bridge',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
