<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when its package is installed or its option enabled — phil/ip-anonymizer for the network
 * of an acceptance, phil/notifications-bundle for the notices, phil/privacy-bundle for the export and the erasure,
 * phil/text-diff and phil/ujo for the texts, the console for the command, the form component and its options resolver
 * for the signup checkbox, the scheduler for the reminders, the translation component and EasyAdmin for the menu. The
 * `service()` function of the container configurator is not seen as declared by `symfony/dependency-injection`
 * although that package defines it. `symfony/ux-twig-component` renders the recipes of the kit the templates call: only
 * the test applications name it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'doctrine/orm',
        'easycorp/easyadmin-bundle',
        'phil/ip-anonymizer',
        'phil/legal-notices',
        'phil/legal-notices-bundle',
        'phil/notifications-bundle',
        'phil/privacy-bundle',
        'phil/text-diff',
        'phil/ujo',
        'symfony/console',
        'symfony/form',
        'symfony/options-resolver',
        'symfony/scheduler',
        'symfony/translation',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
    ]);
