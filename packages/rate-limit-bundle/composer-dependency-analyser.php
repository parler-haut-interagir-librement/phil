<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * bundle registers each only when it is installed or configured — phil/ip-anonymizer for the network of the ip keys,
 * the form component for the form extension, security for the user keys, the translation contracts and Twig for the
 * refusal page and the form error, the options resolver the form extension declares its option with, PSR-3 for the
 * warning of a limiter in observation.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/ip-anonymizer',
        'psr/log',
        'symfony/form',
        'symfony/options-resolver',
        'symfony/security-core',
        'symfony/translation-contracts',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
