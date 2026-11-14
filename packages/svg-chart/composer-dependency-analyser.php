<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The package's declared dependencies, checked against the package installed on its own. The classes of the resvg
 * extension, suggested, are known only where it is loaded.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownClassesRegex('~^Resvg\\\\~');
