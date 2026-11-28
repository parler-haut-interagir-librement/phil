<?php

declare(strict_types=1);

/*
 * The composer.lock of a site, read from bytes nobody wrote. Whatever the bytes, the reader gives package names and
 * versions it checked, sorted, and throws nothing.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SitesConsoleBundle\Domain\Version\Packages;
use Phil\SitesConsoleBundle\Infrastructure\Composer\LockFilePackages;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $versions = LockFilePackages::read($input, true);
    $sorted = $versions;
    ksort($sorted, \SORT_STRING);

    if ($sorted !== $versions) {
        throw new LogicException('The packages are not sorted by name.');
    }

    foreach ($versions as $name => $version) {
        if (!Packages::isName($name) || !Packages::isVersion($version)) {
            throw new LogicException('A package was read with a name or a version that was not checked.');
        }
    }
});
