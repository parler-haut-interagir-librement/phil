<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

/**
 * The root holds no code of its own. Each package declares its own dependencies, which `composer packages` checks
 * against the package installed on its own; the root's `require-dev` only gathers them so that the tests of every
 * package run together.
 */
return new Configuration()
    ->addPathToExclude(__DIR__.'/packages');
