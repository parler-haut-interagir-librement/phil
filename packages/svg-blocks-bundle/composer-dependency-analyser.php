<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The bundle's declared dependencies, checked against the bundle installed on its own.
 *
 * The optional integrations are used by the production code but only required in development and suggested: the
 * form extension is registered only with `phil/ujo-bundle` and Symfony Form, and extends the block editor's field only
 * when `phil/block-editor-bundle` is installed; the preview only with HttpFoundation and the CSRF component; the
 * background pass only with Messenger. The controller's path is given to AssetMapperBundle's extension by its alias,
 * without a class of the component.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'phil/block-editor-bundle',
        'phil/ujo-bundle',
        'symfony/form',
        'symfony/http-foundation',
        'symfony/messenger',
        'symfony/options-resolver',
        'symfony/security-csrf',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
