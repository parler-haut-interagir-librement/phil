<?php

declare(strict_types=1);

/*
 * A manifest read from JSON nobody wrote. Whatever the bytes, the manifest reads, or is refused with InvalidManifest;
 * nothing else is thrown, and a manifest read keeps its start and its shortcuts within its scope, and its paths local.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\PwaBundle\Application\ManifestReader;
use Phil\PwaBundle\Domain\Exception\InvalidManifest;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $manifest = new ManifestReader()->read($data);
    } catch (InvalidManifest) {
        return;
    }

    foreach ([$manifest->id, $manifest->startUrl, $manifest->scope] as $path) {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new LogicException('A path of the manifest left the site.');
        }
    }

    if (!str_starts_with($manifest->startUrl, $manifest->scope)) {
        throw new LogicException('The start address is outside the scope.');
    }
});
