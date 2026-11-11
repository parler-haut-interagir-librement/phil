<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * One exception: `ext-intl` is required in production although only the tests name it. `symfony/intl` calls
 * `Locale::getDefault()` on the library's behalf when `Currency::name()` or `symbol()` receives no locale, so the
 * extension is a dependency the analyser cannot see.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
