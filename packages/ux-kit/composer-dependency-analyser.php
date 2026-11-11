<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The recipes are Twig and JavaScript, copied by ux:install. The PHP is the tests, and src/Test, the browser test case
 * and the page of recipes published for the browser suites of other kits: those run in development only, so the
 * packages they use are development requirements, suggested to the kits that extend them.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages([
        'php-webdriver/webdriver',
        'phpunit/phpunit',
        'psr/container',
        'symfony/http-kernel',
        'symfony/panther',
        'twig/twig',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
