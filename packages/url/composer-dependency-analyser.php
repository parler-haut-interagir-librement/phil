<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own. Two exceptions, both of
 * `ext-intl`, suggested rather than required: `symfony/polyfill-intl-idn` provides `idn_to_ascii()` and `idn_to_utf8()`
 * without it, and looks unused wherever the extension is loaded; `Spoofchecker`, a class of the extension, is used
 * only when it exists.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/polyfill-intl-idn', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-intl', [ErrorType::SHADOW_DEPENDENCY]);
