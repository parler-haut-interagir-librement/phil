<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Doctrine ORM, EasyAdmin, phil/incident-mode, phil/notifications-bundle and phil/privacy-bundle are used by the
 * production code but only required in development and suggested: the bundle registers each integration only when its
 * package is there. The translator and the Twig components are used by the templates, which are not a scanned path.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['doctrine/orm', 'easycorp/easyadmin-bundle', 'phil/incident-mode', 'phil/notifications-bundle', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/translation', 'symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
