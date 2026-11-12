<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations — `phil/browser-security-bundle`, `phil/menu-bundle`, `phil/social-cards`, `phil/social-cards-bundle`,
 * `symfony/http-client-contracts` and `twig/twig` — are used by the production code but only required in development
 * and suggested: the bundle registers the nonce of the policy, the breadcrumb, the share metadata and the Twig functions only when their
 * packages are installed, and the command fetches an address only with an HTTP client.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['phil/browser-security-bundle', 'phil/menu-bundle', 'phil/social-cards', 'phil/social-cards-bundle', 'symfony/http-client-contracts', 'twig/twig'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
