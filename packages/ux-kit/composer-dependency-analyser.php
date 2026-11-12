<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The recipes are Twig and JavaScript, copied by ux:install; the PHP they call from Twig, the bundle and its Twig
 * functions, is under src/. src/Test, the browser test case and the page of recipes published for the browser suites
 * of other kits, runs in development only: the packages it uses are development requirements, suggested to the kits
 * that extend them. AssetMapper, LiipImagine and phil/structured-data are suggested too: image_sources(),
 * image_filters() and image_json_ld() read them when they are there.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'liip/imagine-bundle',
        'php-webdriver/webdriver',
        'phil/structured-data',
        'phpunit/phpunit',
        'psr/container',
        'symfony/asset-mapper',
        'symfony/panther',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
