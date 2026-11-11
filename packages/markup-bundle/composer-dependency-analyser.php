<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `symfony/form` with `symfony/options-resolver`, `twig/twig` and `phil/typography` — are
 * used by the production code but only required in development and suggested: the bundle registers its form type only
 * when Symfony Form is installed, its filters only when Twig is, and its smart punctuation only when `phil/typography`
 * is.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/typography', 'symfony/form', 'symfony/options-resolver', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
