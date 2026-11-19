<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony Security, Messenger, phil/media-bundle, phil/privacy-bundle and phil/settings-bundle are used by the
 * production code but only required in development and suggested: the bundle registers each adapter only when its
 * package is there. AssetMapper is only named by the extension the bundle writes its paths to. The translator and Twig
 * components are used by the templates, which are not a scanned path. `ext-intl` gives the `IntlListFormatter` that
 * twig/intl-extra's `format_list` writes the names of those who reacted with.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/media-bundle', 'phil/privacy-bundle', 'phil/settings-bundle', 'symfony/messenger', 'symfony/security-core'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['symfony/translation', 'symfony/ux-twig-component'], [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::UNUSED_DEPENDENCY]);
