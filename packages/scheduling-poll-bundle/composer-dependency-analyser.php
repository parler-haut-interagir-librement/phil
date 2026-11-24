<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * Twig, `symfony/security-csrf` and `phil/privacy-bundle` are used by classes the bundle registers only when they are
 * there — the functions and pages of the templates, the CSRF token of their forms, the export and the erasure — so they
 * are required in development and suggested. `symfony/ux-twig-component` and `twig/intl-extra` render the templates:
 * only the test application names them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['twig/twig', 'symfony/security-csrf', 'phil/privacy-bundle'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
