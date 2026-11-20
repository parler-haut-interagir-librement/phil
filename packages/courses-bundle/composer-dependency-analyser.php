<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed — the ORM for the schema listener, the console for the commands,
 * Twig and CSRF for the pages, Symfony Security for the current learner and the administration page, phil/audit-bundle
 * for the trail of the second chances, phil/certificates-bundle for the adapter of its CourseCompletions port, and
 * phil/safe-http, with the PSR-3 logger it reports to, for the remote learning record store, phil/privacy-bundle for
 * the SCORM attempts, the cmi5 and xAPI records and the training in the access and erasure requests,
 * phil/notifications-bundle for the reminders of the mandatory training, and Symfony Messenger and Scheduler for their
 * schedule.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'phil/audit-bundle',
        'phil/certificates-bundle',
        'phil/notifications-bundle',
        'phil/privacy-bundle',
        'phil/safe-http',
        'psr/log',
        'symfony/console',
        'symfony/messenger',
        'symfony/scheduler',
        'symfony/security-core',
        'symfony/security-csrf',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
