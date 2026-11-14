<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — the block editor's `@` search, the comments' mentions and authors, the settings'
 * preference, the Twig filter — are used by the production code but only required in development and suggested: the
 * bundle uses them only when they are installed. The paths of AssetMapper are written to its extension by name.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/block-editor-bundle', 'phil/comments-bundle', 'phil/settings-bundle', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
