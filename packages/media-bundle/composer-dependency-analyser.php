<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Twig, Symfony's form, validator, AssetMapper and WebLink, Twig components, EasyAdmin, phil/browser-security-bundle,
 * phil/creation-disclosure and phil/svg-sanitizer are optional: the bundle wires what uses them only when they are
 * installed, so they are development requirements and suggestions. The DOM and libxml extensions are read by the SVG
 * minifier only, wired with phil/svg-sanitizer, which requires them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'symfony/form', 'symfony/options-resolver', 'symfony/validator', 'symfony/asset-mapper', 'symfony/ux-twig-component', 'easycorp/easyadmin-bundle', 'symfony/translation-contracts', 'phil/browser-security-bundle', 'phil/creation-disclosure', 'phil/svg-sanitizer', 'symfony/web-link', 'psr/link'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnExtensions(['ext-dom', 'ext-libxml'], [ErrorType::SHADOW_DEPENDENCY]);
