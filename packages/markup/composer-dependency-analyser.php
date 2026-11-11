<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP, phil/ujo,
 * symfony/html-sanitizer and symfony/yaml.
 *
 * One exception: doctrine/dbal and doctrine/orm are optional, in `require-dev` for the tests and in `suggest` for
 * applications, with a `conflict` that rules out the versions the mapping is not written for. Only `MarkedUpText`
 * names them, in its mapping attributes, which PHP reads only when Doctrine asks for them: the class loads, and
 * `Markup::text()` builds one, without Doctrine installed. The exception is scoped to that file, so an import of
 * Doctrine anywhere else in the library still fails.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackagesAndPaths(
        ['doctrine/dbal', 'doctrine/orm'],
        [__DIR__.'/src/MarkedUpText.php'],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    );
