<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP and symfony/string.
 *
 * `symfony/translation-contracts` is required for `symfony/string`'s `AsciiSlugger`, which implements its
 * `LocaleAwareInterface` and refuses to be built without it, while `symfony/string` only suggests the package; no
 * class of the library names it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackage('symfony/translation-contracts', [ErrorType::UNUSED_DEPENDENCY]);
