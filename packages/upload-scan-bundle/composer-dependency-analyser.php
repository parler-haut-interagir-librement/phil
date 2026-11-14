<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own. Two exceptions: the
 * `service()`, `inline_service()` and `tagged_iterator()` functions of the container configurator, which the analyser
 * does not see as declared by `symfony/dependency-injection` although that package defines them; and the optional
 * integrations — `phil/media-bundle`, `phil/monitoring-bundle` and `symfony/messenger` —,
 * required in development only and suggested, each used only when its bundle is registered or its interface exists;
 * and the quarantine's administration — Twig, Symfony's routing, HTTP kernel, security and CSRF components, EasyAdmin
 * and the translation contracts —, wired only when Twig and the CSRF component are installed; and the purge of the
 * quarantine, `symfony/console`, wired only when the Console component is installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/media-bundle', 'phil/monitoring-bundle', 'symfony/messenger', 'twig/twig', 'symfony/routing', 'symfony/http-kernel', 'symfony/security-core', 'symfony/security-csrf', 'easycorp/easyadmin-bundle', 'symfony/translation-contracts', 'symfony/console'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownFunctions([
        'Symfony\Component\DependencyInjection\Loader\Configurator\inline_service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\service',
        'Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator',
    ]);
