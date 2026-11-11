<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Symfony UX is used by the production code but only required in development and suggested: the bundle registers
 * the component only when it is there.
 *
 * phil/svg-blocks (with phil/ujo) and phil/paged-media are optional integrations, only suggested: the bundle
 * registers the block renderer and the PDF drawing only when their packages are there, and their tests, like
 * phil/svg-blocks-bundle and phil/ujo-bundle in the test kernel, are skipped when they are not. Their classes are
 * unknown to the bundle installed on its own.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/ux-twig-component'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreUnknownClassesRegex('~^Phil\\\\(PagedMedia|SvgBlocks|SvgBlocksBundle|Ujo|UjoBundle)\\\\~');
