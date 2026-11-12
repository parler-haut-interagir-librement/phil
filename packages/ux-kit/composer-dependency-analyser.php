<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The recipes are Twig and JavaScript, copied by ux:install; the PHP they call from Twig, the bundle and its Twig
 * functions, is under src/. src/Test, the browser test case and the page of recipes published for the browser suites
 * of other kits, runs in development only: the packages it uses are development requirements, suggested to the kits
 * that extend them, ext-gd among them for the screenshots. AssetMapper, LiipImagine and phil/structured-data are
 * suggested too: image_sources(), image_filters() and image_json_ld() read them when they are there; and the Console
 * component, for the command that proposes the image recipes.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnExtensions(['ext-gd'], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages([
        'liip/imagine-bundle',
        'php-webdriver/webdriver',
        'phil/structured-data',
        'phpunit/phpunit',
        'psr/container',
        'symfony/asset-mapper',
        'symfony/console',
        'symfony/panther',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
