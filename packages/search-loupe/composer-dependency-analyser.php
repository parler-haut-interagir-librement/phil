<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The adapter's declared dependencies, checked against the package installed on its own: Loupe and the port of
 * `phil/search-bundle` it implements. The test of two processes sharing an index needs ext-pcntl and is skipped
 * without it, so the extension is not required, not even for development.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtension('ext-pcntl', [ErrorType::SHADOW_DEPENDENCY]);
