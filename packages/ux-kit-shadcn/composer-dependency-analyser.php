<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The kit has no PHP of its own: its recipes are Twig and JavaScript, copied by ux:install. Only the tests are PHP,
 * and they use the development requirements alone.
 *
 * The browser suite extends the test case and the page of recipes that phil/ux-kit publishes in its src/Test.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/tests', isDev: true);
