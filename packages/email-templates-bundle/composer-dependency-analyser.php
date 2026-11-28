<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `phil/audit-bundle` for the audit trail, `phil/tenancy-bundle` for the site of the
 * active tenant, `symfony/console` for the commands, `symfony/mailer` for the handler of SendTemplatedEmail,
 * HttpFoundation, HttpKernel, Routing, Security and the CSRF protection for the administration, `twig/cssinliner-extra` for the inlined styles and `twig/intl-extra` for the dates and numbers of a
 * locale — are used by the production code but only required in development and suggested: the bundle registers each
 * only when it is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/audit-bundle',
        'phil/tenancy-bundle',
        'symfony/console',
        'symfony/http-foundation',
        'symfony/http-kernel',
        'symfony/mailer',
        'symfony/routing',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/cssinliner-extra',
        'twig/intl-extra',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
