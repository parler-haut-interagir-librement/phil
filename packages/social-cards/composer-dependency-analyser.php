<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The library's declared dependencies, checked against the library installed on its own: PHP with its ctype, DOM, GD,
 * intl, libxml, mbstring and URI extensions, phil/fonts and phil/url. The classes of `ext-imagick`, a suggested
 * extension that only `ImagickCardRenderer` and `ImagickTextMeasure` use, are unknown where it is not installed.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreUnknownClassesRegex('~^Imagick(Draw|Pixel)?(Exception)?$~');
