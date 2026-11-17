<?php

declare(strict_types=1);

/*
 * The reader of SCORM manifests on documents nobody wrote: each is read into a manifest whose default organization has
 * items and whose launch files stay inside the package, or refused as a package; nothing else is thrown, and nothing
 * warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use Phil\ElearningPackages\Scorm\ScormManifestReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new ScormManifestReader(new PackageLimits(manifestBytes: 65_536, depth: 8, items: 200));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $manifest = $reader->read($input);
    } catch (PackageRefused) {
        return;
    }

    if ([] === $manifest->organization()->items) {
        throw new LogicException('A manifest is read with an organization without items.');
    }

    foreach ($manifest->resources as $resource) {
        if (null !== $resource->href && !PackagePath::isWebAddress($resource->href)) {
            PackagePath::check(explode('?', explode('#', $resource->href)[0])[0]);
        }
    }
});
$config->setMaxLen(4096);
