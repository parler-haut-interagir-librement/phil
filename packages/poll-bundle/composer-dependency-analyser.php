<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Twig is used by `Infrastructure\Twig\PollTwig` but only required in development and suggested through TwigBundle:
 * the bundle registers the functions only when Twig is there. `symfony/ux-twig-component` renders the recipes of the kit
 * the templates call: only the test application names it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::PROD_DEPENDENCY_ONLY_IN_DEV]);
