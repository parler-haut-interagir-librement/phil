<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested:
 * `phil/media-bundle`, the images stored in the media library only when its bundle is registered;
 * `phil/upload-scan-bundle`, each file scanned before it is parsed only when its bundle is registered;
 * `symfony/messenger`, the imports on a worker only when they are enabled; and `twig/twig`, the title of the report in
 * Twig, a service nothing reads without TwigBundle.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/media-bundle', 'phil/upload-scan-bundle', 'symfony/messenger', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
