<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony UX, Messenger, Scheduler, Mailer, Mime, Notifier, Console, HttpFoundation, the security component, AssetMapper
 * and EasyAdmin are used by the production code but only required in development and suggested: the bundle registers
 * the components, the queue, the schedule, the deliveries, the command, the downloads, the access check and the
 * EasyAdmin page only when their packages are there.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'easycorp/easyadmin-bundle',
        'symfony/asset-mapper',
        'symfony/console',
        'symfony/http-foundation',
        'symfony/mailer',
        'symfony/messenger',
        'symfony/mime',
        'symfony/notifier',
        'symfony/scheduler',
        'symfony/security-core',
        'symfony/ux-live-component',
        'symfony/ux-twig-component',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
