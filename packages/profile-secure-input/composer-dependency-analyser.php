<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The profile's declared dependencies, checked against the profile installed on its own.
 *
 * A metapackage has no code: its requires are the members it installs, which only its tests use — they boot an
 * application with the members' bundles and load a class of each library. The libraries that come with a member's bundle
 * are reached through that bundle, as an application reaches them: the profile names the bundle, not the library.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrors([ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV])
    ->ignoreErrorsOnPackages(['phil/bot-detection-bundle', 'phil/rate-limit', 'phil/spam-protection', 'phil/upload-scan', 'phil/url'], [ErrorType::SHADOW_DEPENDENCY]);
