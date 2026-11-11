<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * One exception: the PHPStan extensions under `src/PHPStan/` use PHPStan's own classes — and, for the rule, the
 * `PhpParser\Node` its signature names —, which are not a dependency. PHPStan provides them when it loads the
 * extension (`extension.neon`), and nothing else ever loads these classes, so the traits and attributes stay free
 * of any dependency.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownClassesRegex('~^PHPStan\\\\~')
    ->ignoreErrorsOnPackage('nikic/php-parser', [ErrorType::SHADOW_DEPENDENCY]);
