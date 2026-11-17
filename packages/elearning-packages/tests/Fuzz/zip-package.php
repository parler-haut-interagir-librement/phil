<?php

declare(strict_types=1);

/*
 * The reader of ZIP packages on archives nobody wrote: each is refused as a package, or opened with files whose paths
 * stay inside it and whose contents are read within the limits; nothing else is thrown, and nothing warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ElearningPackages\Archive\ZipPackage;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$file = sys_get_temp_dir().'/phil-courses-fuzz-'.getmypid().'.zip';
$limits = new PackageLimits(archiveBytes: 65_536, entries: 64, entryBytes: 262_144, totalBytes: 524_288, ratio: 50);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($file, $limits): void {
    file_put_contents($file, $input);

    try {
        $package = ZipPackage::open($file, $limits);
    } catch (PackageRefused) {
        return;
    }

    try {
        foreach ($package->files() as $name) {
            PackagePath::check($name);

            try {
                if (strlen($package->read($name)) > $limits->entryBytes) {
                    throw new LogicException('A file larger than the limit is read.');
                }
            } catch (PackageRefused) {
                // A file whose entry lies, or past the total: refused.
            }
        }
    } finally {
        $package->close();
    }
});
$config->setMaxLen(8192);
